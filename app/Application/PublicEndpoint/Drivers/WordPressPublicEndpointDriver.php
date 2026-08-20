<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Drivers;

use App\Application\Applications\Manager\ApplicationManager;
use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\WordPress\PublicEndpoint\WordPressPublicEndpointGateway;
use App\Domain\Application\WordPress\PublicEndpoint\WordPressPublicEndpointInterruptedOperationRecovery;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Services\PlatformInstallationService;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\Server;
use App\Models\User;

final readonly class WordPressPublicEndpointDriver implements PublicEndpointDriverInterface
{
    public function __construct(
        private ApplicationManager $applications,
        private WordPressPublicEndpointGateway $gateway,
        private PlatformInstallationService $platforms,
        private WordPressPublicEndpointInterruptedOperationRecovery $interruptedRecovery,
    ) {}

    public function type(): ApplicationType
    {
        return ApplicationType::WordPress;
    }

    public function name(): string
    {
        return 'WordPress';
    }

    public function description(): string
    {
        return 'وب‌سایت WordPress را با دامنه اختصاصی و HTTPS در دسترس قرار دهید.';
    }

    public function icon(): string
    {
        return 'lucide.newspaper';
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
