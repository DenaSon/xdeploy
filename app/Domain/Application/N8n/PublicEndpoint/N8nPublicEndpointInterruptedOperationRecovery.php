<?php

declare(strict_types=1);

namespace App\Domain\Application\N8n\PublicEndpoint;

interface N8nPublicEndpointInterruptedOperationRecovery
{
    public function recover(): void;
}
