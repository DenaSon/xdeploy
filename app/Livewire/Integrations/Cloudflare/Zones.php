<?php

declare(strict_types=1);

namespace App\Livewire\Integrations\Cloudflare;

use App\Application\Integrations\Cloudflare\CloudflareReadService;
use App\Application\Integrations\Cloudflare\CloudflareZoneService;
use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('دامنه‌های Cloudflare')]
final class Zones extends Component
{
    /** @var list<array{id: string, name: string}> */
    public array $accounts = [];

    /** @var list<array<string, mixed>> */
    public array $zones = [];

    public ?string $selectedZoneId = null;

    public bool $connected = false;

    public bool $needsReconnect = false;

    public bool $canManageZones = false;

    /** @var list<string> */
    public array $missingReadScopes = [];

    /** @var list<string> */
    public array $missingZoneManagementScopes = [];

    public ?string $error = null;

    public ?string $status = null;

    public ?string $lastSyncedAt = null;

    public bool $zoneFormOpen = false;

    public string $zoneAccountId = '';

    public string $zoneDomain = '';

    public ?string $pendingDeleteZoneId = null;

    public function boot(): void
    {
        abort_unless(
            config('services.cloudflare_oauth.enabled', false) === true,
            404,
        );
    }

    public function mount(): void
    {
        $connection = $this->connection();

        if (! $connection instanceof IntegrationConnection) {
            return;
        }

        $this->syncCapabilities($connection);

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

        $this->syncCapabilities($connection);

        if ($this->needsReconnect) {
            $this->accounts = [];
            $this->zones = [];
            $this->selectedZoneId = null;
            $this->error = null;
            $this->resetZoneForm();
            $this->pendingDeleteZoneId = null;

            return;
        }

        $this->loadSnapshot($connection);
    }

    public function selectZone(string $zoneId): void
    {
        if ($this->zoneById($zoneId) === null) {
            $this->error = 'دامنه انتخاب‌شده در داده‌های Cloudflare این اتصال وجود ندارد.';

            return;
        }

        $this->selectedZoneId = $zoneId;
        $this->pendingDeleteZoneId = null;
        $this->status = null;
        $this->error = null;
    }

    public function openCreateZone(): void
    {
        if (! $this->prepareZoneMutation()) {
            return;
        }

        $this->zoneAccountId = (string) ($this->accounts[0]['id'] ?? '');
        $this->zoneDomain = '';
        $this->zoneFormOpen = true;
        $this->pendingDeleteZoneId = null;
        $this->status = null;
        $this->error = null;
        $this->resetValidation();
    }

    public function cancelCreateZone(): void
    {
        $this->resetZoneForm();
    }

    public function createZone(): void
    {
        $connection = $this->connection();

        if (
            ! $connection instanceof IntegrationConnection
            || ! $this->prepareZoneMutation()
        ) {
            return;
        }

        $accountIds = array_values(
            array_filter(
                array_map(
                    static fn (array $account): mixed => $account['id'] ?? null,
                    $this->accounts,
                ),
                'is_string',
            ),
        );

        $this->validate(
            [
                'zoneAccountId' => [
                    'required',
                    'string',
                    Rule::in($accountIds),
                ],
                'zoneDomain' => [
                    'required',
                    'string',
                    'max:253',
                ],
            ],
            [
                'zoneAccountId.required' => 'حساب Cloudflare را انتخاب کنید.',
                'zoneAccountId.in' => 'حساب انتخاب‌شده دیگر در این اتصال در دسترس نیست.',
                'zoneDomain.required' => 'نام دامنه را وارد کنید.',
                'zoneDomain.max' => 'نام دامنه بیش از حد طولانی است.',
            ],
        );

        try {
            $zone = app(CloudflareZoneService::class)->create(
                connection: $connection,
                accountId: $this->zoneAccountId,
                domain: $this->zoneDomain,
            );

            $this->selectedZoneId = is_string($zone['id'] ?? null)
                ? $zone['id']
                : null;
            $this->status = 'دامنه با موفقیت به Cloudflare اضافه شد. Nameserverهای اختصاص‌یافته را روی رجیسترار دامنه تنظیم کنید.';
            $this->error = null;
            $this->resetZoneForm();

            $connection->refresh();
            $this->syncCapabilities($connection);
            $this->loadSnapshot(
                $connection,
                preferredZoneId: $this->selectedZoneId,
            );
        } catch (CloudflareApiException $exception) {
            $this->handleZoneMutationException($exception);
        }
    }

    public function confirmDeleteZone(string $zoneId): void
    {
        if (! $this->prepareZoneMutation()) {
            return;
        }

        if ($this->zoneById($zoneId) === null) {
            $this->error = 'دامنه انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.';

            return;
        }

        $this->pendingDeleteZoneId = $zoneId;
        $this->status = null;
        $this->error = null;
    }

    public function cancelDeleteZone(): void
    {
        $this->pendingDeleteZoneId = null;
    }

    public function deleteZone(): void
    {
        $connection = $this->connection();
        $zoneId = $this->pendingDeleteZoneId;
        $zone = $this->zoneById($zoneId);

        if (
            ! $connection instanceof IntegrationConnection
            || ! is_string($zoneId)
            || $zone === null
            || ! $this->prepareZoneMutation()
        ) {
            if ($zone === null && $this->connected) {
                $this->error = 'دامنه انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.';
            }

            return;
        }

        try {
            app(CloudflareZoneService::class)->delete(
                connection: $connection,
                zoneId: $zoneId,
            );

            $this->pendingDeleteZoneId = null;
            $this->status = 'دامنه با موفقیت از Cloudflare حذف شد.';
            $this->error = null;

            if ($this->selectedZoneId === $zoneId) {
                $this->selectedZoneId = null;
            }

            $connection->refresh();
            $this->syncCapabilities($connection);
            $this->loadSnapshot($connection);
        } catch (CloudflareApiException $exception) {
            $this->handleZoneMutationException($exception);
        }
    }

    public function render(): View
    {
        return view(
            'livewire.integrations.cloudflare.zones',
        );
    }

    private function loadSnapshot(
        IntegrationConnection $connection,
        ?string $preferredZoneId = null,
    ): void {
        $this->error = null;

        try {
            $snapshot = app(CloudflareReadService::class)
                ->snapshot($connection);

            $this->accounts = $snapshot['accounts'];
            $this->zones = $snapshot['zones'];

            $preferredZone = $this->zoneById($preferredZoneId);
            $currentZone = $this->zoneById($this->selectedZoneId);

            if ($preferredZone !== null) {
                $this->selectedZoneId = (string) $preferredZone['id'];
            } elseif ($currentZone === null) {
                $firstZone = $this->zones[0] ?? null;
                $this->selectedZoneId = is_array($firstZone)
                    && is_string($firstZone['id'] ?? null)
                    ? $firstZone['id']
                    : null;
            }

            if ($this->zoneById($this->pendingDeleteZoneId) === null) {
                $this->pendingDeleteZoneId = null;
            }

            $connection->refresh();
            $this->syncCapabilities($connection);
            $this->lastSyncedAt = $connection->last_synced_at?->toIso8601String();
        } catch (CloudflareApiException $exception) {
            $this->handleReadException($exception);
        }
    }

    private function syncCapabilities(
        IntegrationConnection $connection,
    ): void {
        $this->connected = true;

        $scopes = is_array($connection->scopes)
            ? $connection->scopes
            : [];

        $this->missingReadScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::read(),
        );
        $this->missingZoneManagementScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::zoneManagement(),
        );
        $this->needsReconnect = $this->missingReadScopes !== [];
        $this->canManageZones = ! $this->needsReconnect
            && $this->missingZoneManagementScopes === [];
    }

    private function prepareZoneMutation(): bool
    {
        if (! $this->connected || $this->needsReconnect) {
            $this->error = 'ابتدا اتصال Cloudflare را با مجوزهای خواندن لازم دوباره برقرار کنید.';

            return false;
        }

        if (! $this->canManageZones) {
            $this->error = 'برای افزودن یا حذف دامنه باید مجوز zone.write به اتصال Cloudflare اضافه شود.';

            return false;
        }

        if ($this->accounts === []) {
            $this->error = 'حساب Cloudflare قابل استفاده‌ای در این اتصال پیدا نشد.';

            return false;
        }

        return true;
    }

    private function handleReadException(
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
            $this->canManageZones = false;
        }

        $this->error = match ($exception->reason) {
            CloudflareApiException::UNAUTHORIZED,
            CloudflareApiException::REFRESH_FAILED => 'دسترسی Cloudflare منقضی یا لغو شده است. اتصال را دوباره برقرار کنید.',
            CloudflareApiException::FORBIDDEN,
            CloudflareApiException::MISSING_SCOPES => 'مجوزهای خواندن Cloudflare کامل نیست. اتصال را دوباره برقرار کنید.',
            CloudflareApiException::RATE_LIMITED => 'Cloudflare موقتاً محدودیت درخواست اعمال کرده است. کمی بعد دوباره تلاش کنید.',
            CloudflareApiException::RESOURCE_LIMIT => 'تعداد منابع Cloudflare از سقف ایمن همگام‌سازی بیشتر است.',
            default => 'دریافت دامنه‌ها از Cloudflare ناموفق بود. دوباره تلاش کنید.',
        };
    }

    private function handleZoneMutationException(
        CloudflareApiException $exception,
    ): void {
        if (
            in_array(
                $exception->reason,
                [
                    CloudflareApiException::UNAUTHORIZED,
                    CloudflareApiException::REFRESH_FAILED,
                ],
                true,
            )
        ) {
            $this->needsReconnect = true;
            $this->canManageZones = false;
        }

        if (
            in_array(
                $exception->reason,
                [
                    CloudflareApiException::FORBIDDEN,
                    CloudflareApiException::MISSING_SCOPES,
                ],
                true,
            )
        ) {
            $this->canManageZones = false;
            $this->missingZoneManagementScopes = CloudflareScopes::zoneManagement();
        }

        $this->error = match ($exception->reason) {
            CloudflareApiException::UNAUTHORIZED,
            CloudflareApiException::REFRESH_FAILED => 'دسترسی Cloudflare منقضی یا لغو شده است. اتصال را دوباره برقرار کنید.',
            CloudflareApiException::FORBIDDEN,
            CloudflareApiException::MISSING_SCOPES => 'مجوز Zone Write برای این اتصال فعال نیست. دسترسی Cloudflare را به‌روزرسانی کنید.',
            CloudflareApiException::INVALID_REQUEST => 'دامنه یا حساب انتخاب‌شده معتبر نیست یا Cloudflare آن را نمی‌پذیرد.',
            CloudflareApiException::RATE_LIMITED => 'Cloudflare موقتاً محدودیت درخواست اعمال کرده است. کمی بعد دوباره تلاش کنید.',
            default => 'اعمال تغییر دامنه در Cloudflare ناموفق بود. دوباره تلاش کنید.',
        };
    }

    /** @return array<string, mixed>|null */
    private function zoneById(?string $zoneId): ?array
    {
        if (! is_string($zoneId) || $zoneId === '') {
            return null;
        }

        foreach ($this->zones as $zone) {
            if (($zone['id'] ?? null) === $zoneId) {
                return $zone;
            }
        }

        return null;
    }

    private function resetZoneForm(): void
    {
        $this->zoneFormOpen = false;
        $this->zoneAccountId = '';
        $this->zoneDomain = '';
        $this->resetValidation();
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
        $this->canManageZones = false;
        $this->missingReadScopes = [];
        $this->missingZoneManagementScopes = [];
        $this->accounts = [];
        $this->zones = [];
        $this->selectedZoneId = null;
        $this->pendingDeleteZoneId = null;
        $this->lastSyncedAt = null;
        $this->error = null;
        $this->status = null;
        $this->resetZoneForm();
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
