<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Drivers;

use App\Application\Applications\Manager\ApplicationManager;
use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointGateway;
use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointInterruptedOperationRecovery;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Services\PlatformInstallationService;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\Server;
use App\Models\User;

final readonly class N8nPublicEndpointDriver implements PublicEndpointDriverInterface
{
    public function __construct(
        private ApplicationManager $applications,
        private N8nPublicEndpointGateway $gateway,
        private PlatformInstallationService $platforms,
        private N8nPublicEndpointInterruptedOperationRecovery $interruptedRecovery,
    ) {}

    public function type(): ApplicationType
    {
        return ApplicationType::N8n;
    }

    public function name(): string
    {
        return 'n8n';
    }

    public function description(): string
    {
        return 'رابط n8n و Webhookهای آن را روی دامنه امن خود در دسترس قرار دهید.';
    }

    public function icon(): string
    {
        return 'lucide.workflow';
    }

    public function openUrl(PublicEndpointDomain $domain): string
    {
        return "https://{$domain->value}/";
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

        return new PublicEndpointApplicationStatus(
            application: $application,
            endpoint: $application->isInstalled()
                ? $this->gateway->inspect()
                : new PublicEndpointRuntimeInfo(
                    PublicEndpointRuntimeState::Disabled,
                ),
        );
    }

    public function preflight(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointPreflightResult {
        $current = $this->status($user, $server);

        if (! $current->application->isInstalled()) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $dns = $this->gateway->preflightDns(
            domain: $domain,
            knownServerAddress: $server->host,
        );

        return new PublicEndpointPreflightResult(
            dns: $dns,
            server: $dns->ready()
                ? $this->gateway->preflightServer()
                : null,
        );
    }

    public function enable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        $this->status($user, $server);
        $this->interruptedRecovery->recover();

        $current = $this->status($user, $server);

        if (! in_array(
            $current->endpoint->state,
            [
                PublicEndpointRuntimeState::Disabled,
                PublicEndpointRuntimeState::ManagedIncomplete,
            ],
            true,
        )) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        $preflight = $this->preflight(
            user: $user,
            server: $server,
            domain: $domain,
        );

        if (! $preflight->ready()) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $this->platforms->ensure(
            PlatformType::Caddy,
        );

        $endpoint = $this->gateway->enable($domain);
        $application = $this->applications->inspect(
            user: $user,
            server: $server,
            type: $this->type(),
        );

        return new PublicEndpointApplicationStatus(
            application: $application,
            endpoint: $endpoint,
        );
    }

    public function disable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        $this->status($user, $server);
        $this->interruptedRecovery->recover();

        $current = $this->status($user, $server);

        if ($current->endpoint->state === PublicEndpointRuntimeState::Disabled) {
            return $current;
        }

        if (! in_array(
            $current->endpoint->state,
            [
                PublicEndpointRuntimeState::Enabled,
                PublicEndpointRuntimeState::ManagedIncomplete,
            ],
            true,
        )) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        if (
            $current->endpoint->state === PublicEndpointRuntimeState::Enabled
            && $current->endpoint->domain !== $domain->value
        ) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        $endpoint = $this->gateway->disable($domain);
        $application = $this->applications->inspect(
            user: $user,
            server: $server,
            type: $this->type(),
        );

        return new PublicEndpointApplicationStatus(
            application: $application,
            endpoint: $endpoint,
        );
    }
}
