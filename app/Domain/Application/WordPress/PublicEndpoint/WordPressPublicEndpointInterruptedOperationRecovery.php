<?php

declare(strict_types=1);

namespace App\Domain\Application\WordPress\PublicEndpoint;

interface WordPressPublicEndpointInterruptedOperationRecovery
{
    public function recover(): void;
}
