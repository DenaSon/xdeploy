<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Contracts;

use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\Server;
use App\Models\User;

interface PublicEndpointDriverInterface
{
    public function type(): ApplicationType;

    public function name(): string;

    public function description(): string;

    public function icon(): string;

    public function openUrl(PublicEndpointDomain $domain): string;

    public function status(User $user, Server $server): PublicEndpointApplicationStatus;

    public function preflight(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointPreflightResult;

    public function enable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus;

    public function disable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus;
}
