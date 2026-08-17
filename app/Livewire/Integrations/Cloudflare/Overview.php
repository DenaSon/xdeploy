<?php

declare(strict_types=1);

namespace App\Livewire\Integrations\Cloudflare;

use App\Application\Integrations\Cloudflare\CloudflareDnsService;
use App\Application\Integrations\Cloudflare\CloudflareReadService;
use App\Domain\Integration\Cloudflare\CloudflareDnsRecordTypes;
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
#[Title('Cloudflare')]
final class Overview extends Component
{
    /** @var list<array{id: string, name: string}> */
    public array $accounts = [];

    /** @var list<array<string, mixed>> */
    public array $zones = [];

    /** @var list<array<string, mixed>> */
    public array $dnsRecords = [];

    /** @var list<string> */
    public array $manageableDnsTypes = [];

    public ?string $selectedZoneId = null;

    public ?string $error = null;

    public ?string $dnsStatus = null;

    public ?string $lastSyncedAt = null;

    public bool $connected = false;

    public bool $needsReconnect = false;

    public bool $canManageDns = false;

    /** @var list<string> */
    public array $missingScopes = [];

    /** @var list<string> */
    public array $missingDnsWriteScopes = [];

    public bool $dnsFormOpen = false;

    public ?string $editingDnsRecordId = null;

    public string $dnsType = CloudflareDnsRecordTypes::A;

    public string $dnsName = '@';

    public string $dnsContent = '';

    public string $dnsTtl = '1';

    public bool $dnsProxied = false;

    public string $dnsPriority = '';

    public string $dnsComment = '';

    public function mount(): void
    {
        $this->manageableDnsTypes = CloudflareDnsRecordTypes::manageable();

        $connection = $this->connection();

        if (! $connection instanceof IntegrationConnection) {
            return;
        }

        $this->syncConnectionCapabilities($connection);

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

        $this->syncConnectionCapabilities($connection);

        if ($this->needsReconnect) {
            $this->accounts = [];
            $this->zones = [];
            $this->dnsRecords = [];
            $this->selectedZoneId = null;
            $this->error = null;
            $this->resetDnsForm();

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
        $this->dnsStatus = null;
        $this->resetDnsForm();
        $this->loadDnsRecords($connection);
    }

    public function openCreateDnsRecord(): void
    {
        if (! $this->prepareDnsMutation()) {
            return;
        }

        $this->editingDnsRecordId = null;
        $this->dnsType = CloudflareDnsRecordTypes::A;
        $this->dnsName = '@';
        $this->dnsContent = '';
        $this->dnsTtl = '1';
        $this->dnsProxied = false;
        $this->dnsPriority = '';
        $this->dnsComment = '';
        $this->dnsFormOpen = true;
        $this->dnsStatus = null;
        $this->resetValidation();
    }

    public function editDnsRecord(string $recordId): void
    {
        if (! $this->prepareDnsMutation()) {
            return;
        }

        $record = $this->dnsRecordById($recordId);

        if ($record === null) {
            $this->error = 'رکورد DNS انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.';

            return;
        }

        $type = is_string($record['type'] ?? null)
            ? strtoupper($record['type'])
            : '';

        if (! CloudflareDnsRecordTypes::supports($type)) {
            $this->error = 'ویرایش این نوع رکورد در Coreflare هنوز پشتیبانی نمی‌شود.';

            return;
        }

        $this->editingDnsRecordId = $recordId;
        $this->dnsType = $type;
        $this->dnsName = $this->editableRecordName(
            (string) ($record['name'] ?? ''),
        );
        $this->dnsContent = (string) ($record['content'] ?? '');
        $this->dnsTtl = (string) ($record['ttl'] ?? 1);
        $this->dnsProxied = ($record['proxied'] ?? null) === true;
        $this->dnsPriority = is_numeric($record['priority'] ?? null)
            ? (string) $record['priority']
            : '';
        $this->dnsComment = is_string($record['comment'] ?? null)
            ? $record['comment']
            : '';
        $this->dnsFormOpen = true;
        $this->dnsStatus = null;
        $this->resetValidation();
    }

    public function cancelDnsRecordForm(): void
    {
        $this->resetDnsForm();
    }

    public function updatedDnsType(string $type): void
    {
        if (! CloudflareDnsRecordTypes::proxiable($type)) {
            $this->dnsProxied = false;
        }

        if (! CloudflareDnsRecordTypes::requiresPriority($type)) {
            $this->dnsPriority = '';
        }
    }

    public function saveDnsRecord(): void
    {
        $connection = $this->connection();
        $zone = $this->zoneById($this->selectedZoneId);

        if (
            ! $connection instanceof IntegrationConnection
            || $zone === null
            || ! $this->prepareDnsMutation()
        ) {
            return;
        }

        $this->validate(
            $this->dnsRules(),
            $this->dnsMessages(),
        );

        $zoneId = (string) $zone['id'];
        $zoneName = (string) $zone['name'];
        $priority = trim($this->dnsPriority) === ''
            ? null
            : (int) $this->dnsPriority;

        try {
            $dns = app(CloudflareDnsService::class);

            if ($this->editingDnsRecordId === null) {
                $dns->create(
                    connection: $connection,
                    zoneId: $zoneId,
                    zoneName: $zoneName,
                    type: $this->dnsType,
                    name: $this->dnsName,
                    content: $this->dnsContent,
                    ttl: (int) $this->dnsTtl,
                    proxied: $this->dnsProxied,
                    priority: $priority,
                    comment: $this->dnsComment,
                );

                $this->dnsStatus = 'رکورد DNS با موفقیت ساخته شد.';
            } else {
                if ($this->dnsRecordById($this->editingDnsRecordId) === null) {
                    $this->error = 'رکورد DNS انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.';

                    return;
                }

                $dns->update(
                    connection: $connection,
                    zoneId: $zoneId,
                    zoneName: $zoneName,
                    recordId: $this->editingDnsRecordId,
                    type: $this->dnsType,
                    name: $this->dnsName,
                    content: $this->dnsContent,
                    ttl: (int) $this->dnsTtl,
                    proxied: $this->dnsProxied,
                    priority: $priority,
                    comment: $this->dnsComment,
                );

                $this->dnsStatus = 'رکورد DNS با موفقیت به‌روزرسانی شد.';
            }

            $this->error = null;
            $this->resetDnsForm();
            $connection->refresh();
            $this->syncConnectionCapabilities($connection);
            $this->loadDnsRecords($connection);
        } catch (CloudflareApiException $exception) {
            $this->handleDnsMutationException($exception);
        }
    }

    public function deleteDnsRecord(string $recordId): void
    {
        $connection = $this->connection();
        $zone = $this->zoneById($this->selectedZoneId);
        $record = $this->dnsRecordById($recordId);

        if (
            ! $connection instanceof IntegrationConnection
            || $zone === null
            || $record === null
            || ! $this->prepareDnsMutation()
        ) {
            if ($record === null && $this->connected) {
                $this->error = 'رکورد DNS انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.';
            }

            return;
        }

        try {
            app(CloudflareDnsService::class)->delete(
                connection: $connection,
                zoneId: (string) $zone['id'],
                recordId: $recordId,
            );

            if ($this->editingDnsRecordId === $recordId) {
                $this->resetDnsForm();
            }

            $this->dnsStatus = 'رکورد DNS با موفقیت حذف شد.';
            $this->error = null;
            $connection->refresh();
            $this->syncConnectionCapabilities($connection);
            $this->loadDnsRecords($connection);
        } catch (CloudflareApiException $exception) {
            $this->handleDnsMutationException($exception);
        }
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
            $this->syncConnectionCapabilities($connection);
            $this->lastSyncedAt = $connection->last_synced_at?->toIso8601String();

            if ($this->selectedZoneId !== null) {
                $this->loadDnsRecords($connection);
            } else {
                $this->dnsRecords = [];
            }
        } catch (CloudflareApiException $exception) {
            $this->handleReadApiException($exception);
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
            $this->handleReadApiException($exception);
        }
    }

    private function syncConnectionCapabilities(
        IntegrationConnection $connection,
    ): void {
        $this->connected = true;

        $scopes = is_array($connection->scopes)
            ? $connection->scopes
            : [];

        $this->missingScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::read(),
        );
        $this->missingDnsWriteScopes = CloudflareScopes::missing(
            $scopes,
            CloudflareScopes::dnsWrite(),
        );
        $this->needsReconnect = $this->missingScopes !== [];
        $this->canManageDns = ! $this->needsReconnect
            && $this->missingDnsWriteScopes === [];
    }

    private function prepareDnsMutation(): bool
    {
        if (! $this->connected || $this->needsReconnect) {
            $this->error = 'ابتدا اتصال Cloudflare را با مجوزهای لازم دوباره برقرار کنید.';

            return false;
        }

        if ($this->zoneById($this->selectedZoneId) === null) {
            $this->error = 'ابتدا یک دامنه معتبر را انتخاب کنید.';

            return false;
        }

        if (! $this->canManageDns) {
            $this->error = 'برای مدیریت DNS باید مجوز dns.write به اتصال Cloudflare اضافه شود.';

            return false;
        }

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    private function dnsRules(): array
    {
        $contentRules = match (strtoupper($this->dnsType)) {
            CloudflareDnsRecordTypes::A => ['required', 'ipv4'],
            CloudflareDnsRecordTypes::AAAA => ['required', 'ipv6'],
            CloudflareDnsRecordTypes::CNAME,
            CloudflareDnsRecordTypes::MX => ['required', 'string', 'max:255'],
            default => ['required', 'string', 'max:4096'],
        };

        return [
            'dnsType' => [
                'required',
                Rule::in($this->manageableDnsTypes),
            ],
            'dnsName' => ['required', 'string', 'max:255'],
            'dnsContent' => $contentRules,
            'dnsTtl' => [
                'required',
                'integer',
                static function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ): void {
                    $ttl = is_numeric($value)
                        ? (int) $value
                        : 0;

                    if ($ttl !== 1 && ($ttl < 60 || $ttl > 86400)) {
                        $fail('TTL باید روی Auto یا بین 60 تا 86400 ثانیه باشد.');
                    }
                },
            ],
            'dnsProxied' => ['boolean'],
            'dnsPriority' => CloudflareDnsRecordTypes::requiresPriority(
                $this->dnsType,
            )
                ? ['required', 'integer', 'between:0,65535']
                : ['nullable', 'integer', 'between:0,65535'],
            'dnsComment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    private function dnsMessages(): array
    {
        return [
            'dnsType.required' => 'نوع رکورد را انتخاب کنید.',
            'dnsType.in' => 'این نوع رکورد برای مدیریت مستقیم پشتیبانی نمی‌شود.',
            'dnsName.required' => 'نام رکورد را وارد کنید.',
            'dnsName.max' => 'نام رکورد بیش از حد طولانی است.',
            'dnsContent.required' => 'مقدار رکورد را وارد کنید.',
            'dnsContent.ipv4' => 'برای رکورد A یک IPv4 معتبر وارد کنید.',
            'dnsContent.ipv6' => 'برای رکورد AAAA یک IPv6 معتبر وارد کنید.',
            'dnsContent.max' => 'مقدار رکورد بیش از حد طولانی است.',
            'dnsPriority.required' => 'برای رکورد MX مقدار Priority لازم است.',
            'dnsPriority.integer' => 'Priority باید عدد صحیح باشد.',
            'dnsPriority.between' => 'Priority باید بین 0 تا 65535 باشد.',
            'dnsComment.max' => 'توضیح رکورد بیش از حد طولانی است.',
        ];
    }

    private function handleReadApiException(
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
            $this->canManageDns = false;
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

    private function handleDnsMutationException(
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
            $this->canManageDns = false;
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
            $this->canManageDns = false;
            $this->missingDnsWriteScopes = CloudflareScopes::dnsWrite();
        }

        $this->error = match ($exception->reason) {
            CloudflareApiException::UNAUTHORIZED,
            CloudflareApiException::REFRESH_FAILED => 'دسترسی Cloudflare منقضی یا لغو شده است. اتصال را دوباره برقرار کنید.',
            CloudflareApiException::FORBIDDEN,
            CloudflareApiException::MISSING_SCOPES => 'مجوز DNS Write برای این اتصال فعال نیست. دسترسی Cloudflare را به‌روزرسانی کنید.',
            CloudflareApiException::INVALID_REQUEST => 'اطلاعات رکورد DNS معتبر نیست. مقادیر فرم را بررسی کنید.',
            CloudflareApiException::RATE_LIMITED => 'Cloudflare موقتاً محدودیت درخواست اعمال کرده است. کمی بعد دوباره تلاش کنید.',
            default => 'اعمال تغییر DNS در Cloudflare ناموفق بود. دوباره تلاش کنید.',
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

    /** @return array<string, mixed>|null */
    private function dnsRecordById(string $recordId): ?array
    {
        foreach ($this->dnsRecords as $record) {
            if (($record['id'] ?? null) === $recordId) {
                return $record;
            }
        }

        return null;
    }

    private function editableRecordName(string $name): string
    {
        $zone = $this->zoneById($this->selectedZoneId);
        $zoneName = is_string($zone['name'] ?? null)
            ? strtolower($zone['name'])
            : '';
        $name = strtolower(trim($name, " .\t\n\r\0\x0B"));

        if ($zoneName === '' || $name === '') {
            return $name;
        }

        if ($name === $zoneName) {
            return '@';
        }

        $suffix = '.'.$zoneName;

        if (str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }

        return $name;
    }

    private function resetDnsForm(): void
    {
        $this->dnsFormOpen = false;
        $this->editingDnsRecordId = null;
        $this->dnsType = CloudflareDnsRecordTypes::A;
        $this->dnsName = '@';
        $this->dnsContent = '';
        $this->dnsTtl = '1';
        $this->dnsProxied = false;
        $this->dnsPriority = '';
        $this->dnsComment = '';
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
        $this->canManageDns = false;
        $this->missingScopes = [];
        $this->missingDnsWriteScopes = [];
        $this->accounts = [];
        $this->zones = [];
        $this->dnsRecords = [];
        $this->selectedZoneId = null;
        $this->lastSyncedAt = null;
        $this->error = null;
        $this->dnsStatus = null;
        $this->resetDnsForm();
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
