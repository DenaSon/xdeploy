<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Drivers;

use App\Application\Applications\Manager\ApplicationManager;
use App\Application\Applications\Marzban\MarzbanManager;
use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointServerPreflightResult;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\Server;
use App\Models\User;

final readonly class MarzbanPublicEndpointDriver implements PublicEndpointDriverInterface
{
    public function __construct(
        private ApplicationManager $applications,
        private MarzbanManager $manager,
    ) {}

    public function type(): ApplicationType
    {
        return ApplicationType::Marzban;
    }

    public function name(): string
    {
        return 'Marzban';
    }

    public function description(): string
    {
        return 'پنل مدیریت Marzban را روی دامنه یا زیردامنه خود منتشر کنید.';
    }

    public function icon(): string
    {
        return 'lucide.box';
    }

    public function openUrl(PublicEndpointDomain $domain): string
    {
        return "https://{$domain->value}/dashboard/";
    }

    public function status(
        User $user,
        Server $server,
    ): PublicEndpointApplicationStatus {
        $application = $this->applications->inspect(
            user: $user,
            server: $server,
            type: $this->type(),
        );

        if ($application->isNotInstalled()) {
            return new PublicEndpointApplicationStatus(
                application: $application,
                endpoint: new PublicEndpointRuntimeInfo(
                    PublicEndpointRuntimeState::Disabled,
                ),
            );
        }

        $management = $this->manager->overview(
            user: $user,
            server: $server,
        );

        return new PublicEndpointApplicationStatus(
            application: $management->application,
            endpoint: $this->mapRuntime(
                state: $management->https->state,
                domain: $management->https->domain,
            ),
        );
    }

    public function preflight(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointPreflightResult {
        if (! $this->status($user, $server)->application->isInstalled()) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        try {
            $result = $this->manager->preflightHttps(
                user: $user,
                server: $server,
                domain: $domain->value,
            );

            return new PublicEndpointPreflightResult(
                dns: $this->mapDns($result->dns),
                server: $result->server === null
                    ? null
                    : $this->mapServer($result->server),
            );
        } catch (MarzbanHttpsPreflightException) {
            throw PublicEndpointOperationException::preflightFailed();
        }
    }

    public function enable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        try {
            $management = $this->manager->enableHttps(
                user: $user,
                server: $server,
                domain: $domain->value,
            );

            return new PublicEndpointApplicationStatus(
                application: $management->application,
                endpoint: $this->mapRuntime(
                    state: $management->https->state,
                    domain: $management->https->domain,
                ),
            );
        } catch (MarzbanHttpsPreflightException) {
            throw PublicEndpointOperationException::preflightFailed();
        } catch (MarzbanHttpsApplyException $exception) {
            throw $this->mapApplyException($exception);
        }
    }

    public function disable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        try {
            $management = $this->manager->disableHttps(
                user: $user,
                server: $server,
                domain: $domain->value,
            );

            return new PublicEndpointApplicationStatus(
                application: $management->application,
                endpoint: $this->mapRuntime(
                    state: $management->https->state,
                    domain: $management->https->domain,
                ),
            );
        } catch (MarzbanHttpsApplyException $exception) {
            throw $this->mapApplyException($exception);
        }
    }

    private function mapRuntime(
        MarzbanHttpsState $state,
        ?string $domain,
    ): PublicEndpointRuntimeInfo {
        return new PublicEndpointRuntimeInfo(
            state: match ($state) {
                MarzbanHttpsState::Disabled => PublicEndpointRuntimeState::Disabled,
                MarzbanHttpsState::Enabled => PublicEndpointRuntimeState::Enabled,
                MarzbanHttpsState::ManagedIncomplete => PublicEndpointRuntimeState::ManagedIncomplete,
                MarzbanHttpsState::ManagedExternally => PublicEndpointRuntimeState::ManagedExternally,
                MarzbanHttpsState::Misconfigured => PublicEndpointRuntimeState::Misconfigured,
                MarzbanHttpsState::Unknown => PublicEndpointRuntimeState::Unknown,
            },
            domain: $domain,
        );
    }

    private function mapDns(
        MarzbanHttpsDnsPreflightResult $result,
    ): PublicEndpointDnsPreflightResult {
        return new PublicEndpointDnsPreflightResult(
            domain: $result->domain,
            serverIpv4Address: $result->serverIpv4Address,
            resolvedIpv4Addresses: $result->resolvedIpv4Addresses,
            resolvedIpv6Addresses: $result->resolvedIpv6Addresses,
        );
    }

    private function mapServer(
        MarzbanHttpsServerPreflightResult $result,
    ): PublicEndpointServerPreflightResult {
        return new PublicEndpointServerPreflightResult(
            layoutState: $result->layoutState->value,
            layoutSupported: $result->layoutSupported(),
            managedCaddyDetected: $result->managedCaddyDetected,
            hasPortConflict: $result->hasPortConflict(),
            ready: $result->ready(),
            ports: [
                80 => $result->port80->toArray(),
                443 => $result->port443->toArray(),
            ],
        );
    }

    private function mapApplyException(
        MarzbanHttpsApplyException $exception,
    ): PublicEndpointOperationException {
        return match ($exception->failure) {
            MarzbanHttpsApplyFailure::ExistingConfiguration => PublicEndpointOperationException::existingConfiguration(),
            MarzbanHttpsApplyFailure::OperationInProgress => PublicEndpointOperationException::operationInProgress(),
            MarzbanHttpsApplyFailure::EnvironmentUnavailable => PublicEndpointOperationException::environmentUnavailable(),
            MarzbanHttpsApplyFailure::CandidateValidation => PublicEndpointOperationException::candidateValidationFailed(),
            MarzbanHttpsApplyFailure::Mutation => PublicEndpointOperationException::mutationFailed(
                recoveryAttempted: $exception->recoveryAttempted(),
                recovered: $exception->recovered(),
            ),
            MarzbanHttpsApplyFailure::Verification => PublicEndpointOperationException::verificationFailed(
                $exception->recovered(),
            ),
        };
    }
}
