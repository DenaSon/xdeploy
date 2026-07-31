<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

trait ReportsSshUnavailable
{
    protected function reportSshUnavailable(
        string $message,
        ?int $retryAfter = null,
    ): void {
        $this->dispatch(
            'ssh-unavailable',
            message: $message,
            retryAfter: $retryAfter,
        );
    }
}
