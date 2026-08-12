<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class SshMarzbanHttpsPreflight
{
    private const int PUBLIC_ADDRESS_UNAVAILABLE = 41;

    private const int DNS_LOOKUP_UNAVAILABLE = 42;

    private const int SERVER_INSPECTION_UNAVAILABLE = 51;

    private const string DNS_COMMAND = <<<'BASH'
domain=%s
server_ipv4=%s

if [ -z "$server_ipv4" ]; then
    server_ipv4="$(
        curl \
            --ipv4 \
            --fail \
            --silent \
            --show-error \
            --connect-timeout 4 \
            --max-time 8 \
            'https://api.ipify.org'
    )" || exit 41
fi

server_ipv4="$(printf '%%s' "$server_ipv4" | tr -d '\r\n')"

if [ -z "$server_ipv4" ]; then
    exit 41
fi

if ! command -v getent >/dev/null 2>&1; then
    exit 42
fi

printf 'xdeploy_dns_preflight=1\n'
printf 'server_ipv4=%%s\n' "$server_ipv4"

getent ahostsv4 "$domain" 2>/dev/null |
    awk '{print $1}' |
    sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'a=%%s\n' "$address"
    done

getent ahostsv6 "$domain" 2>/dev/null |
    awk 'tolower($1) !~ /^::ffff:/ {print $1}' |
    sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'aaaa=%%s\n' "$address"
    done
BASH;

    private const string SERVER_COMMAND = <<<'BASH'
marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
env_file="$marzban_path/.env"

if ! command -v docker >/dev/null 2>&1 ||
    ! docker compose version >/dev/null 2>&1 ||
    ! command -v ss >/dev/null 2>&1; then
    exit 51
fi

layout_state='supported'

if [ ! -d "$marzban_path" ]; then
    layout_state='missing'
elif [ ! -r "$marzban_path" ] || [ ! -x "$marzban_path" ] ||
    [ ! -e "$compose_file" ] || [ ! -e "$env_file" ] ||
    [ ! -r "$compose_file" ] || [ ! -r "$env_file" ] ||
    [ -L "$compose_file" ] || [ -L "$env_file" ]; then
    layout_state='unreadable'
else
    compose_services="$(
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            -p marzban \
            config --services 2>/dev/null
    )" || layout_state='invalid_compose'

    if [ "$layout_state" = 'supported' ] &&
        ! printf '%s\n' "$compose_services" | grep -Fxq 'marzban'; then
        layout_state='unsupported_compose'
    fi

    if [ "$layout_state" = 'supported' ]; then
        compose_images="$(
            docker compose \
                --env-file "$env_file" \
                -f "$compose_file" \
                -p marzban \
                config --images 2>/dev/null
        )" || layout_state='invalid_compose'

        if [ "$layout_state" = 'supported' ] &&
            ! printf '%s\n' "$compose_images" |
                grep -Eq '(^|/)gozargah/marzban([:@]|$)'; then
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

    if ! listener="$(
        ss -H -lntp "sport = :$port" 2>/dev/null
    )"; then
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
    elif printf '%s' "$listener_lower" |
        grep -Eq 'apache2|httpd'; then
        printf 'conflict|apache'
    elif printf '%s' "$listener_lower" | grep -Fq 'haproxy'; then
        printf 'conflict|haproxy'
    elif printf '%s' "$listener_lower" | grep -Fq 'caddy'; then
        printf 'conflict|caddy'
    elif printf '%s' "$listener_lower" |
        grep -Eq 'docker-proxy|containerd'; then
        printf 'conflict|docker'
    else
        printf 'conflict|other'
    fi
}

port_80="$(classify_port 80)"
port_443="$(classify_port 443)"

printf 'xdeploy_server_preflight=1\n'
printf 'layout_state=%s\n' "$layout_state"
printf 'port_80_state=%s\n' "${port_80%%|*}"
printf 'port_80_owner=%s\n' "${port_80#*|}"
printf 'port_443_state=%s\n' "${port_443%%|*}"
printf 'port_443_owner=%s\n' "${port_443#*|}"
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function dns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        $knownIpv4 = filter_var(
            $knownServerAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
                | FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE,
        );

        $result = $this->ssh->executeWithResult(
            command: sprintf(
                self::DNS_COMMAND,
                escapeshellarg($domain->value),
                escapeshellarg(is_string($knownIpv4) ? $knownIpv4 : ''),
            ),
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw match ($result->exitCode) {
                self::PUBLIC_ADDRESS_UNAVAILABLE => MarzbanHttpsPreflightException::publicAddressUnavailable(),
                self::DNS_LOOKUP_UNAVAILABLE => MarzbanHttpsPreflightException::dnsLookupUnavailable(),
                default => MarzbanHttpsPreflightException::invalidResponse(),
            };
        }

        return $this->parseDns(
            domain: $domain,
            output: $result->output,
        );
    }

    public function server(): MarzbanHttpsServerPreflightResult
    {
        $result = $this->privileged->executeWithResult(
            command: self::SERVER_COMMAND,
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw match ($result->exitCode) {
                self::SERVER_INSPECTION_UNAVAILABLE => MarzbanHttpsPreflightException::serverInspectionUnavailable(),
                default => MarzbanHttpsPreflightException::invalidResponse(),
            };
        }

        return $this->parseServer($result->output);
    }

    private function parseDns(
        MarzbanDomain $domain,
        string $output,
    ): MarzbanHttpsDnsPreflightResult {
        $markerFound = false;
        $serverIpv4 = null;
        $resolvedIpv4 = [];
        $resolvedIpv6 = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            if ($key === 'xdeploy_dns_preflight' && $value === '1') {
                $markerFound = true;

                continue;
            }

            if (
                $key === 'server_ipv4'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                        | FILTER_FLAG_NO_PRIV_RANGE
                        | FILTER_FLAG_NO_RES_RANGE,
                ) !== false
            ) {
                $serverIpv4 = $value;

                continue;
            }

            if (
                $key === 'a'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4,
                ) !== false
            ) {
                $resolvedIpv4[] = $value;

                continue;
            }

            if (
                $key === 'aaaa'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6,
                ) !== false
                && ! $this->isIpv4MappedIpv6($value)
            ) {
                $resolvedIpv6[] = $value;
            }
        }

        if (! $markerFound || $serverIpv4 === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        $resolvedIpv4 = array_values(array_unique($resolvedIpv4));
        $resolvedIpv6 = array_values(array_unique($resolvedIpv6));

        sort($resolvedIpv4);
        sort($resolvedIpv6);

        return new MarzbanHttpsDnsPreflightResult(
            domain: $domain->value,
            serverIpv4Address: $serverIpv4,
            resolvedIpv4Addresses: $resolvedIpv4,
            resolvedIpv6Addresses: $resolvedIpv6,
        );
    }

    private function parseServer(
        string $output,
    ): MarzbanHttpsServerPreflightResult {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            $values[$key] = $value;
        }

        if (($values['xdeploy_server_preflight'] ?? null) !== '1') {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        $layoutState = MarzbanHttpsLayoutState::tryFrom(
            $values['layout_state'] ?? '',
        );

        if ($layoutState === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        return new MarzbanHttpsServerPreflightResult(
            layoutState: $layoutState,
            managedCaddyDetected: false,
            port80: $this->parsePortInfo(
                port: 80,
                values: $values,
            ),
            port443: $this->parsePortInfo(
                port: 443,
                values: $values,
            ),
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    private function parsePortInfo(
        int $port,
        array $values,
    ): MarzbanHttpsPortInfo {
        $state = MarzbanHttpsPortState::tryFrom(
            $values["port_{$port}_state"] ?? '',
        );

        $owner = MarzbanHttpsPortOwner::tryFrom(
            $values["port_{$port}_owner"] ?? '',
        );

        if ($state === null || $owner === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        return new MarzbanHttpsPortInfo(
            port: $port,
            state: $state,
            owner: $owner,
        );
    }

    private function isIpv4MappedIpv6(string $address): bool
    {
        $packedAddress = inet_pton($address);

        if ($packedAddress === false || strlen($packedAddress) !== 16) {
            return false;
        }

        return substr($packedAddress, 0, 10) === str_repeat("\0", 10)
            && substr($packedAddress, 10, 2) === "\xff\xff";
    }
}
