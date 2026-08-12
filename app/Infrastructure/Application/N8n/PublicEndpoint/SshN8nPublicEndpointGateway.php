<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointGateway;
use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointServerPreflightResult;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\PublicEndpoint\SshPublicEndpointDnsPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use Throwable;

final readonly class SshN8nPublicEndpointGateway implements N8nPublicEndpointGateway
{
    private const string SITE_KEY = 'n8n';

    private const string UPSTREAM = '127.0.0.1:5678';

    private const string SERVER_PREFLIGHT_COMMAND = <<<'BASH'
app_dir='/opt/n8n'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"

if ! command -v docker >/dev/null 2>&1 ||
    ! docker compose version >/dev/null 2>&1 ||
    ! command -v ss >/dev/null 2>&1; then
    exit 51
fi

layout_state='supported'

if [ ! -d "$app_dir" ]; then
    layout_state='missing'
elif [ ! -r "$app_dir" ] || [ ! -x "$app_dir" ] ||
    [ ! -e "$compose_file" ] || [ ! -e "$env_file" ] ||
    [ ! -r "$compose_file" ] || [ ! -r "$env_file" ] ||
    [ -L "$compose_file" ] || [ -L "$env_file" ]; then
    layout_state='unreadable'
else
    compose_services="$(
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            -p n8n \
            config --services 2>/dev/null
    )" || layout_state='invalid_compose'

    if [ "$layout_state" = 'supported' ] &&
        ! printf '%s\n' "$compose_services" | grep -Fxq 'n8n'; then
        layout_state='unsupported_compose'
    fi

    if [ "$layout_state" = 'supported' ]; then
        compose_images="$(
            docker compose \
                --env-file "$env_file" \
                -f "$compose_file" \
                -p n8n \
                config --images 2>/dev/null
        )" || layout_state='invalid_compose'

        if [ "$layout_state" = 'supported' ] &&
            ! printf '%s\n' "$compose_images" |
                grep -Eq '(^|/)n8nio/n8n([:@]|$)'; then
            layout_state='unsupported_compose'
        fi
    fi
fi

docker_bindings="$(
    docker ps --format '{{.ID}}|{{.Names}}|{{.Ports}}' 2>/dev/null
)" || exit 51

classify_port() {
    port="$1"

    if printf '%s\n' "$docker_bindings" |
        awk -F '|' -v needle=":$port->" '
            index($3, needle) > 0 { found = 1; exit }
            END { exit found ? 0 : 1 }
        '; then
        printf 'conflict|docker'
        return
    fi

    if ! listener="$(ss -H -lntp "sport = :$port" 2>/dev/null)"; then
        printf 'unknown|unknown'
        return
    fi

    if [ -z "$listener" ]; then
        printf 'available|none'
        return
    fi

    listener_lower="$(printf '%s' "$listener" | tr '[:upper:]' '[:lower:]')"

    if printf '%s' "$listener_lower" | grep -Fq 'nginx'; then
        printf 'conflict|nginx'
    elif printf '%s' "$listener_lower" | grep -Eq 'apache2|httpd'; then
        printf 'conflict|apache'
    elif printf '%s' "$listener_lower" | grep -Fq 'haproxy'; then
        printf 'conflict|haproxy'
    elif printf '%s' "$listener_lower" | grep -Fq 'caddy'; then
        printf 'conflict|caddy'
    elif printf '%s' "$listener_lower" | grep -Eq 'docker-proxy|containerd'; then
        printf 'conflict|docker'
    else
        printf 'conflict|other'
    fi
}

port_80="$(classify_port 80)"
port_443="$(classify_port 443)"

printf 'xdeploy_n8n_server_preflight=1\n'
printf 'layout_state=%s\n' "$layout_state"
printf 'port_80_state=%s\n' "${port_80%%|*}"
printf 'port_80_owner=%s\n' "${port_80#*|}"
printf 'port_443_state=%s\n' "${port_443%%|*}"
printf 'port_443_owner=%s\n' "${port_443#*|}"
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
        private SshPublicEndpointDnsPreflight $dnsPreflight,
        private SshN8nPublicEndpointRuntimeManager $runtime,
        private CaddySiteReaderInterface $caddySites,
        private CaddySiteManagerInterface $caddySiteManager,
    ) {}

    public function inspect(): PublicEndpointRuntimeInfo
    {
        try {
            $runtime = $this->runtime->inspect();
            $domain = $runtime->domain();

            if (! $this->caddySites->exists($this->siteKey())) {
                if (! $runtime->hasPublicConfiguration()) {
                    return new PublicEndpointRuntimeInfo(
                        PublicEndpointRuntimeState::Disabled,
                    );
                }

                if (
                    $domain !== null
                    && $runtime->matches($domain)
                    && $this->httpsReachable($domain)
                ) {
                    return new PublicEndpointRuntimeInfo(
                        state: PublicEndpointRuntimeState::ManagedExternally,
                        domain: $domain,
                    );
                }

                return new PublicEndpointRuntimeInfo(
                    state: PublicEndpointRuntimeState::Misconfigured,
                    domain: $domain,
                );
            }

            if ($domain !== null) {
                $site = $this->site(
                    PublicEndpointDomain::from($domain),
                );

                if (
                    $runtime->matches($domain)
                    && $this->caddySites->matches($site)
                    && $this->httpsReachable($domain)
                ) {
                    return new PublicEndpointRuntimeInfo(
                        state: PublicEndpointRuntimeState::Enabled,
                        domain: $domain,
                    );
                }
            }

            return new PublicEndpointRuntimeInfo(
                state: PublicEndpointRuntimeState::ManagedIncomplete,
                domain: $domain,
            );
        } catch (PublicEndpointOperationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw PublicEndpointOperationException::environmentUnavailable();
        }
    }

    public function preflightDns(
        PublicEndpointDomain $domain,
        ?string $knownServerAddress = null,
    ): PublicEndpointDnsPreflightResult {
        return $this->dnsPreflight->check(
            domain: $domain,
            knownServerAddress: $knownServerAddress,
        );
    }

    public function preflightServer(): PublicEndpointServerPreflightResult
    {
        $result = $this->privileged->executeWithResult(
            command: self::SERVER_PREFLIGHT_COMMAND,
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $values = $this->parseKeyValues($result->output);

        if (($values['xdeploy_n8n_server_preflight'] ?? null) !== '1') {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $layoutState = $values['layout_state'] ?? '';

        if (! in_array(
            $layoutState,
            [
                'supported',
                'missing',
                'unreadable',
                'invalid_compose',
                'unsupported_compose',
            ],
            true,
        )) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $managedCaddy = $this->caddySites->environmentReady();
        $ports = [
            80 => $this->portInfo(80, $values, $managedCaddy),
            443 => $this->portInfo(443, $values, $managedCaddy),
        ];

        $hasConflict = ($ports[80]['has_conflict'] ?? true) === true
            || ($ports[443]['has_conflict'] ?? true) === true;
        $layoutSupported = $layoutState === 'supported';

        return new PublicEndpointServerPreflightResult(
            layoutState: $layoutState,
            layoutSupported: $layoutSupported,
            managedCaddyDetected: $managedCaddy,
            hasPortConflict: $hasConflict,
            ready: $layoutSupported
                && ($ports[80]['available_for_xdeploy'] ?? false) === true
                && ($ports[443]['available_for_xdeploy'] ?? false) === true,
            ports: $ports,
        );
    }

    public function enable(
        PublicEndpointDomain $domain,
    ): PublicEndpointRuntimeInfo {
        if (! $this->caddySites->environmentReady()) {
            throw PublicEndpointOperationException::environmentUnavailable();
        }

        $key = $this->siteKey();
        $site = $this->site($domain);
        $siteExisted = $this->caddySites->exists($key);

        if ($siteExisted && ! $this->caddySites->matches($site)) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        try {
            $siteMutation = $this->caddySiteManager->upsert($site);
        } catch (CaddySiteMutationException $exception) {
            throw $this->mapCaddyMutationException($exception);
        }

        try {
            $runtimeMutation = $this->runtime->prepareEnabled($domain);
        } catch (PublicEndpointOperationException $exception) {
            $siteRecovered = $this->recoverCreatedSite(
                key: $key,
                existedBefore: $siteExisted,
                changed: $siteMutation->changed,
            );

            if (! $siteRecovered) {
                throw PublicEndpointOperationException::mutationFailed(
                    recoveryAttempted: true,
                    recovered: false,
                );
            }

            throw $exception;
        }

        if (! $this->httpsReachable($domain->value)) {
            $runtimeRecovered = $this->runtime->restore($runtimeMutation);
            $siteRecovered = $this->recoverCreatedSite(
                key: $key,
                existedBefore: $siteExisted,
                changed: $siteMutation->changed,
            );

            throw PublicEndpointOperationException::verificationFailed(
                $runtimeRecovered && $siteRecovered,
            );
        }

        $this->runtime->commit($runtimeMutation);

        return new PublicEndpointRuntimeInfo(
            state: PublicEndpointRuntimeState::Enabled,
            domain: $domain->value,
        );
    }

    public function disable(
        PublicEndpointDomain $domain,
    ): PublicEndpointRuntimeInfo {
        $key = $this->siteKey();
        $site = $this->site($domain);
        $siteExists = $this->caddySites->exists($key);

        if ($siteExists && ! $this->caddySites->matches($site)) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        $runtimeMutation = $this->runtime->prepareDisabled();

        if ($siteExists) {
            try {
                $this->caddySiteManager->remove($key);
            } catch (CaddySiteMutationException $exception) {
                $runtimeRecovered = $this->runtime->restore($runtimeMutation);

                if (! $runtimeRecovered) {
                    throw PublicEndpointOperationException::mutationFailed(
                        recoveryAttempted: true,
                        recovered: false,
                    );
                }

                throw $this->mapCaddyMutationException($exception);
            }
        }

        $this->runtime->commit($runtimeMutation);

        return new PublicEndpointRuntimeInfo(
            PublicEndpointRuntimeState::Disabled,
        );
    }

    private function siteKey(): CaddySiteKey
    {
        return CaddySiteKey::from(self::SITE_KEY);
    }

    private function site(PublicEndpointDomain $domain): CaddySite
    {
        return CaddySite::reverseProxy(
            key: $this->siteKey(),
            domain: $domain->value,
            upstream: self::UPSTREAM,
        );
    }

    /**
     * @param  array<string, string>  $values
     * @return array{port:int,state:string,owner:string,available_for_xdeploy:bool,has_conflict:bool}
     */
    private function portInfo(
        int $port,
        array $values,
        bool $managedCaddy,
    ): array {
        $state = $values["port_{$port}_state"] ?? '';
        $owner = $values["port_{$port}_owner"] ?? '';

        if (! in_array($state, ['available', 'conflict', 'unknown'], true)) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        if ($state === 'conflict' && $owner === 'caddy' && $managedCaddy) {
            $state = 'managed';
            $owner = 'xdeploy_caddy';
        }

        $available = in_array($state, ['available', 'managed'], true);

        return [
            'port' => $port,
            'state' => $state,
            'owner' => $owner,
            'available_for_xdeploy' => $available,
            'has_conflict' => $state === 'conflict' || $state === 'unknown',
        ];
    }

    private function mapCaddyMutationException(
        CaddySiteMutationException $exception,
    ): PublicEndpointOperationException {
        return match ($exception->failure) {
            CaddySiteMutationFailure::Environment => PublicEndpointOperationException::environmentUnavailable(),
            CaddySiteMutationFailure::CandidateValidation => PublicEndpointOperationException::candidateValidationFailed(),
            CaddySiteMutationFailure::Busy => PublicEndpointOperationException::operationInProgress(),
            CaddySiteMutationFailure::Mutation,
            CaddySiteMutationFailure::Reload,
            CaddySiteMutationFailure::Recovery => PublicEndpointOperationException::mutationFailed(
                recoveryAttempted: $exception->recoveryAttempted(),
                recovered: $exception->configurationRestored()
                    && $exception->serviceRecovered(),
            ),
        };
    }

    private function recoverCreatedSite(
        CaddySiteKey $key,
        bool $existedBefore,
        bool $changed,
    ): bool {
        if (! $changed || $existedBefore) {
            return true;
        }

        try {
            $this->caddySiteManager->remove($key);

            return true;
        } catch (CaddySiteMutationException) {
            return false;
        }
    }

    private function httpsReachable(string $domain): bool
    {
        $result = $this->ssh->executeWithResult(
            command: sprintf(
                <<<'BASH'
http_code="$(
    curl \
        --location \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%%{http_code}' \
        --connect-timeout 3 \
        --max-time 8 \
        %s 2>/dev/null
)"

case "$http_code" in
    2??|3??) exit 0 ;;
    *) exit 1 ;;
esac
BASH,
                escapeshellarg("https://{$domain}/"),
            ),
            timeout: SSHTimeout::NORMAL,
        );

        return $result->successful();
    }

    /** @return array<string, string> */
    private function parseKeyValues(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
