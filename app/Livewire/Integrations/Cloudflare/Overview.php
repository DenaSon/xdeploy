<?php

declare(strict_types=1);

namespace App\Livewire\Integrations\Cloudflare;

use App\Application\Integrations\Cloudflare\CloudflareReadService;
use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('Cloudflare')]
final class Overview extends Component
{
    /** @var list<array{id: string, name: string}> */
    public array $accounts = [];

    /** @var list<array<string, mixed>> */
    public array $zones = [];

    /** @var list<array<string, mixed>> */
    public array $dnsRecords = [];

    public ?string $selectedZoneId = null;

    public ?string $error = null;

    public ?string $lastSyncedAt = null;

    public bool $connected = false;

    public bool $needsReconnect = false;

    /** @var list<string> */
    public array $missingScopes = [];

    public function mount(): void
    {
        $connection = $this->connection();

        if (! $connection instanceof IntegrationConnection) {
            return;
        }

        $this->connected = true;
        $this->missingScopes = CloudflareScopes::missing(
            is_array($connection->scopes)
                ? $connection->scopes
                : [],
        );
        $this->needsReconnect = $this->missingScopes !== [];

        if ($this->needsReconnect) {
            return;
        }

        $this->loadSnapshot($connection);
    }

    public function refreshData(): void
    {
        $connection = $this->connection();

        if (! $connection instanceof IntegrationConnection) {
            $this->resetDisconnectedState();

            return;
        }

        $this->connected = true;
        $this->missingScopes = CloudflareScopes::missing(
            is_array($connection->scopes)
                ? $connection->scopes
                : [],
        );
        $this->needsReconnect = $this->missingScopes !== [];

        if ($this->needsReconnect) {
            $this->accounts = [];
            $this->zones = [];
            $this->dnsRecords = [];
            $this->selectedZoneId = null;
            $this->error = null;

            return;
        }

        $this->loadSnapshot($connection);
    }

    public function selectZone(string $zoneId): void
    {
        $zone = $this->zoneById($zoneId);

        if ($zone === null) {
            $this->error = 'دامنه انتخاب‌شده در داده‌های Cloudflare این اتصال وجود ندارد.';

            return;
        }

        $connection = $this->connection();

        if (! $connection instanceof IntegrationConnection) {
            $this->resetDisconnectedState();

            return;
        }

        $this->selectedZoneId = $zoneId;
        $this->loadDnsRecords($connection);
    }

    public function render(): View
    {
        return view(
            'livewire.integrations.cloudflare.overview',
        );
    }

    private function loadSnapshot(
        IntegrationConnection $connection,
    ): void {
        $this->error = null;

        try {
            $snapshot = app(CloudflareReadService::class)
                ->snapshot($connection);

            $this->accounts = $snapshot['accounts'];
            $this->zones = $snapshot['zones'];

            if ($this->zoneById($this->selectedZoneId) === null) {
                $firstZone = $this->zones[0] ?? null;
                $this->selectedZoneId = is_array($firstZone)
                    && is_string($firstZone['id'] ?? null)
                    ? $firstZone['id']
                    : null;
            }

            $connection->refresh();
            $this->lastSyncedAt = $connection->last_synced_at?->toIso8601String();

            if ($this->selectedZoneId !== null) {
                $this->loadDnsRecords($connection);
            } else {
                $this->dnsRecords = [];
            }
        } catch (CloudflareApiException $exception) {
            $this->handleApiException($exception);
        }
    }

    private function loadDnsRecords(
        IntegrationConnection $connection,
    ): void {
        if ($this->selectedZoneId === null) {
            $this->dnsRecords = [];

            return;
        }

        $this->error = null;

        try {
            $this->dnsRecords = app(CloudflareReadService::class)
                ->dnsRecords(
                    $connection,
                    $this->selectedZoneId,
                );
        } catch (CloudflareApiException $exception) {
            $this->dnsRecords = [];
            $this->handleApiException($exception);
        }
    }

    private function handleApiException(
        CloudflareApiException $exception,
    ): void {
        if (
            in_array(
                $exception->reason,
                [
                    CloudflareApiException::UNAUTHORIZED,
                    CloudflareApiException::FORBIDDEN,
                    CloudflareApiException::MISSING_SCOPES,
                    CloudflareApiException::REFRESH_FAILED,
                ],
                true,
            )
        ) {
            $this->needsReconnect = true;
        }

        $this->error = match ($exception->reason) {
            CloudflareApiException::UNAUTHORIZED,
            CloudflareApiException::REFRESH_FAILED => 'دسترسی Cloudflare منقضی یا لغو شده است. اتصال را دوباره برقرار کنید.',
            CloudflareApiException::FORBIDDEN,
            CloudflareApiException::MISSING_SCOPES => 'مجوزهای خواندن Cloudflare کامل نیست. اتصال را با دسترسی‌های جدید دوباره برقرار کنید.',
            CloudflareApiException::RATE_LIMITED => 'Cloudflare موقتاً محدودیت درخواست اعمال کرده است. کمی بعد دوباره تلاش کنید.',
            CloudflareApiException::RESOURCE_LIMIT => 'تعداد منابع Cloudflare از سقف ایمن همگام‌سازی این صفحه بیشتر است.',
            default => 'دریافت اطلاعات از Cloudflare ناموفق بود. دوباره تلاش کنید.',
        };
    }

    /** @return array<string, mixed>|null */
    private function zoneById(?string $zoneId): ?array
    {
        if ($zoneId === null || $zoneId === '') {
            return null;
        }

        foreach ($this->zones as $zone) {
            if (($zone['id'] ?? null) === $zoneId) {
                return $zone;
            }
        }

        return null;
    }

    private function connection(): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->ownedBy($this->user())
            ->where(
                'provider',
                IntegrationProvider::Cloudflare->value,
            )
            ->first();
    }

    private function resetDisconnectedState(): void
    {
        $this->connected = false;
        $this->needsReconnect = false;
        $this->missingScopes = [];
        $this->accounts = [];
        $this->zones = [];
        $this->dnsRecords = [];
        $this->selectedZoneId = null;
        $this->lastSyncedAt = null;
        $this->error = null;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
