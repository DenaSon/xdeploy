<?php

declare(strict_types=1);

namespace App\Livewire\Integrations;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Models\IntegrationConnection;
use App\Models\TelegramConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('یکپارچه‌سازی‌ها')]
final class Index extends Component
{
    public function render(
        CloudflareOAuthClient $cloudflare,
        TelegramBotClient $telegram,
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
            CloudflareScopes::read(),
        );
        $missingDnsWriteScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::dnsWrite(),
        );
        $missingZoneManagementScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::zoneManagement(),
        );

        $configuredScopes = $cloudflare->scopes();
        $zoneManagementConfigured = CloudflareScopes::missing(
            $configuredScopes,
            CloudflareScopes::zoneManagement(),
        ) === [];

        $telegramConnection = TelegramConnection::query()
            ->ownedBy($user)
            ->first();

        return view(
            'livewire.integrations.index',
            [
                'cloudflareConfigured' => $cloudflare->configured(),
                'cloudflareReadConfigured' => $cloudflare->configuredForRead(),
                'cloudflareDnsWriteConfigured' => $cloudflare->configuredForDnsWrite(),
                'cloudflareZoneManagementConfigured' => $zoneManagementConfigured,
                'cloudflareConnected' => $connection !== null,
                'cloudflareReadReady' => $connection !== null
                    && $missingReadScopes === [],
                'cloudflareDnsWriteReady' => $connection !== null
                    && $missingDnsWriteScopes === [],
                'cloudflareZoneManagementReady' => $connection !== null
                    && $missingZoneManagementScopes === [],
                'cloudflareConnectedAt' => $connection?->connected_at,
                'cloudflareScopes' => $scopes,
                'cloudflareMissingReadScopes' => $missingReadScopes,
                'cloudflareMissingDnsWriteScopes' => $missingDnsWriteScopes,
                'cloudflareMissingZoneManagementScopes' => $missingZoneManagementScopes,
                'telegramConfigured' => $telegram->configured(),
                'telegramConnected' => $telegramConnection !== null,
                'telegramConnectedAt' => $telegramConnection?->connected_at,
                'telegramUsername' => $telegramConnection?->username,
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
