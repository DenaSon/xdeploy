<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsInspectionException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Infrastructure\Application\Marzban\Https\DTOs\MarzbanHttpsRuntimeInfo;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsPreflight;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsRuntimeManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;
use Throwable;

final readonly class SshMarzbanHttpsGateway implements MarzbanHttpsGateway
{
    private const string SITE_KEY = 'marzban';

    private const string UPSTREAM = '127.0.0.1:8000';

    public function __construct(
        private SSHConnectionInterface $ssh,
        private SshMarzbanHttpsPreflight $preflight,
        private SshMarzbanHttpsRuntimeManager $runtime,
        private CaddySiteReaderInterface $caddySites,
        private CaddySiteManagerInterface $caddySiteManager,
    ) {}

    public function inspect(): MarzbanHttpsInfo
    {
        try {
            if (! $this->caddySites->exists($this->siteKey())) {
                return $this->inspectWithoutManagedSite(
                    $this->runtime->inspect(),
                );
            }

            $runtime = $this->runtime->inspect();
            $domain = $this->domainFromUrl($runtime->subscriptionUrl);

            if ($domain === null) {
                return new MarzbanHttpsInfo(
                    state: MarzbanHttpsState::Misconfigured,
                );
            }

            $site = $this->site(
                MarzbanDomain::from($domain),
            );

            if (
                $runtime->usesManagedReverseProxyRuntime()
                && $this->caddySites->matches($site)
                && $this->httpsReachable($domain)
            ) {
                return new MarzbanHttpsInfo(
                    state: MarzbanHttpsState::Enabled,
                    domain: $domain,
                );
            }

            return new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Misconfigured,
                domain: $domain,
            );
        } catch (MarzbanHttpsInspectionException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MarzbanHttpsInspectionException::failed();
        }
    }

    public function preflightDns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        return $this->preflight->dns(
            domain: $domain,
            knownServerAddress: $knownServerAddress,
        );
    }

    public function preflightServer(): MarzbanHttpsServerPreflightResult
    {
        $preflight = $this->preflight->server();

        if (! $this->caddySites->environmentReady()) {
            return $preflight;
        }

        return new MarzbanHttpsServerPreflightResult(
            layoutState: $preflight->layoutState,
            managedCaddyDetected: true,
            port80: $this->normalizeManagedCaddyPort(
                $preflight->port80,
            ),
            port443: $this->normalizeManagedCaddyPort(
                $preflight->port443,
            ),
        );
    }

    public function enable(
        MarzbanDomain $domain,
    ): MarzbanHttpsApplyResult {
        if (! $this->caddySites->environmentReady()) {
            throw MarzbanHttpsApplyException::environmentUnavailable();
        }

        $key = $this->siteKey();

        if ($this->caddySites->exists($key)) {
            throw MarzbanHttpsApplyException::existingConfiguration();
        }

        $site = $this->site($domain);

        try {
            $siteMutation = $this->caddySiteManager->upsert($site);
        } catch (CaddySiteMutationException $exception) {
            throw $this->mapCaddyMutationException($exception);
        }

        try {
            $runtimeMutation = $this->runtime->prepare($domain);
        } catch (MarzbanHttpsApplyException $exception) {
            if (! $this->removeSiteAfterFailure($key, $siteMutation->changed)) {
                throw MarzbanHttpsApplyException::mutationFailed(
                    new MarzbanHttpsRecoveryResult(
                        configurationRestored: false,
                        servicesRecovered: false,
                    ),
                );
            }

            throw $exception;
        }

        if (! $this->runtime->verifyHttps($domain)) {
            $siteRecovered = $this->removeSiteAfterFailure(
                $key,
                $siteMutation->changed,
            );

            $runtimeRecovery = $this->runtime->restore(
                $runtimeMutation,
            );

            throw MarzbanHttpsApplyException::verificationFailed(
                new MarzbanHttpsRecoveryResult(
                    configurationRestored: $siteRecovered
                        && $runtimeRecovery->configurationRestored,
                    servicesRecovered: $siteRecovered
                        && $runtimeRecovery->servicesRecovered,
                ),
            );
        }

        $this->runtime->commit($runtimeMutation);

        return new MarzbanHttpsApplyResult(
            domain: $domain->value,
            panelUrl: "https://{$domain->value}/dashboard/",
            configurationChanged: $siteMutation->changed
                || $runtimeMutation->configurationChanged,
        );
    }

    private function inspectWithoutManagedSite(
        MarzbanHttpsRuntimeInfo $runtime,
    ): MarzbanHttpsInfo {
        $domain = $this->domainFromUrl($runtime->subscriptionUrl);
        $hasCertificate = $runtime->sslCertificateFile !== null;
        $hasKey = $runtime->sslKeyFile !== null;

        if ($hasCertificate !== $hasKey) {
            return new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Misconfigured,
                domain: $domain,
            );
        }

        if ($hasCertificate && $hasKey) {
            return new MarzbanHttpsInfo(
                state: MarzbanHttpsState::ManagedExternally,
                domain: $domain,
            );
        }

        if ($domain !== null && $this->httpsReachable($domain)) {
            return new MarzbanHttpsInfo(
                state: MarzbanHttpsState::ManagedExternally,
                domain: $domain,
            );
        }

        if (
            $runtime->uds !== null
            || $runtime->usesManagedReverseProxyRuntime()
            || $domain !== null
        ) {
            return new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Misconfigured,
                domain: $domain,
            );
        }

        return new MarzbanHttpsInfo(
            state: MarzbanHttpsState::Disabled,
        );
    }

    private function siteKey(): CaddySiteKey
    {
        return CaddySiteKey::from(self::SITE_KEY);
    }

    private function site(
        MarzbanDomain $domain,
    ): CaddySite {
        return CaddySite::reverseProxy(
            key: $this->siteKey(),
            domain: $domain->value,
            upstream: self::UPSTREAM,
        );
    }

    private function normalizeManagedCaddyPort(
        MarzbanHttpsPortInfo $port,
    ): MarzbanHttpsPortInfo {
        if (
            $port->state === MarzbanHttpsPortState::Conflict
            && $port->owner === MarzbanHttpsPortOwner::Caddy
        ) {
            return new MarzbanHttpsPortInfo(
                port: $port->port,
                state: MarzbanHttpsPortState::Managed,
                owner: MarzbanHttpsPortOwner::XDeployCaddy,
            );
        }

        return $port;
    }

    private function mapCaddyMutationException(
        CaddySiteMutationException $exception,
    ): MarzbanHttpsApplyException {
        return match ($exception->failure) {
            CaddySiteMutationFailure::Environment => MarzbanHttpsApplyException::environmentUnavailable(),
            CaddySiteMutationFailure::CandidateValidation => MarzbanHttpsApplyException::candidateValidationFailed(),
            CaddySiteMutationFailure::Busy => MarzbanHttpsApplyException::operationInProgress(),
            CaddySiteMutationFailure::Mutation,
            CaddySiteMutationFailure::Reload,
            CaddySiteMutationFailure::Recovery => MarzbanHttpsApplyException::mutationFailed(
                $exception->recoveryAttempted()
                    ? new MarzbanHttpsRecoveryResult(
                        configurationRestored: $exception->configurationRestored(),
                        servicesRecovered: $exception->serviceRecovered(),
                    )
                    : null,
            ),
        };
    }

    private function removeSiteAfterFailure(
        CaddySiteKey $key,
        bool $changed,
    ): bool {
        if (! $changed) {
            return true;
        }

        try {
            $this->caddySiteManager->remove($key);

            return true;
        } catch (CaddySiteMutationException) {
            return false;
        }
    }

    private function domainFromUrl(?string $url): ?string
    {
        $url = trim($url ?? '');

        if ($url === '') {
            return null;
        }

        $domain = parse_url($url, PHP_URL_HOST);

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return strtolower($domain);
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
                escapeshellarg(
                    "https://{$domain}/dashboard/",
                ),
            ),
            timeout: SSHTimeout::NORMAL,
        );

        return $result->successful();
    }
}
