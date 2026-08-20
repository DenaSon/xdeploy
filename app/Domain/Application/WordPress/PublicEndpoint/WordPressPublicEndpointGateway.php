<?php

declare(strict_types=1);

namespace App\Domain\Application\WordPress\PublicEndpoint;

use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointServerPreflightResult;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;

interface WordPressPublicEndpointGateway
{
    public function inspect(): PublicEndpointRuntimeInfo;

    public function preflightDns(
        PublicEndpointDomain $domain,
        ?string $knownServerAddress = null,
    ): PublicEndpointDnsPreflightResult;

    public function preflightServer(): PublicEndpointServerPreflightResult;

    public function enable(PublicEndpointDomain $domain): PublicEndpointRuntimeInfo;

    public function disable(PublicEndpointDomain $domain): PublicEndpointRuntimeInfo;
}
