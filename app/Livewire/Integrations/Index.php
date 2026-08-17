<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('اتصال‌ها')]
final class Index extends Component
{
    public function render(
        CloudflareOAuthClient $cloudflare,
    ): View {
        $user = $this->user();

        $connection = IntegrationConnection::query()
            ->ownedBy($user)
            ->where(
                'provider',
                IntegrationProvider::Cloudflare->value,
            )
            ->first();

        $scopes = is_array($connection?->scopes)
            ? $connection->scopes
            : [];

        $missingReadScopes = CloudflareScopes::missing(
            $scopes,
        );

        return view(
            'livewire.integrations.index',
            [
                'cloudflareConfigured' => $cloudflare->configured(),
                'cloudflareReadConfigured' => $cloudflare->configuredForRead(),
                'cloudflareConnected' => $connection !== null,
                'cloudflareReadReady' => $connection !== null
                    && $missingReadScopes === [],
                'cloudflareConnectedAt' => $connection?->connected_at,
                'cloudflareScopes' => $scopes,
                'cloudflareMissingReadScopes' => $missingReadScopes,
            ],
        );
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
