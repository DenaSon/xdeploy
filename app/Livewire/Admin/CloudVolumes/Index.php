<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CloudVolumes;

use App\Application\Cloud\Volumes\ArvanVolumeAuditService;
use App\Application\Cloud\Volumes\DeleteArvanAuditedVolumeAction;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
#[Title('بررسی Volumeها')]
final class Index extends Component
{
    /** @var list<array<string, bool|int|string|null>> */
    public array $items = [];

    public string $search = '';

    public string $filter = 'all';

    public ?string $lastCheckedAt = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->loadAudit();
    }

    public function refreshAudit(): void
    {
        $this->message = null;
        $this->error = null;
        $this->loadAudit();
    }

    public function deleteVolume(
        string $region,
        string $volumeId,
        DeleteArvanAuditedVolumeAction $deleteVolume,
    ): void {
        $this->adminUser();
        $this->message = null;
        $this->error = null;

        try {
            $deleted = $deleteVolume->handle(
                region: $region,
                volumeId: $volumeId,
            );

            $this->loadAudit();

            $this->message = $deleted
                ? 'Volume از آروان حذف و نبودن آن تأیید شد.'
                : 'درخواست حذف به آروان ارسال شد؛ Volume هنوز در حال حذف است. بعداً بازبینی کنید.';
        } catch (CloudValidationException) {
            $this->error = 'این Volume در وضعیت فعلی قابل حذف نیست. ابتدا گزارش را بازبینی کنید.';
        } catch (Throwable $exception) {
            report($exception);

            $this->error = 'حذف Volume انجام نشد. وضعیت ArvanCloud و لاگ‌های سیستم را بررسی کنید.';
        }
    }

    public function render(): View
    {
        return view(
            'livewire.admin.cloud-volumes.index',
            [
                'visibleItems' => $this->filteredItems(),
            ],
        );
    }

    private function loadAudit(): void
    {
        try {
            $items = app(ArvanVolumeAuditService::class)->audit();

            $this->items = array_map(
                static fn ($item): array => $item->toArray(),
                $items,
            );
            $this->lastCheckedAt = now()->format('Y-m-d H:i:s');
        } catch (Throwable $exception) {
            report($exception);

            $this->error = 'دریافت گزارش Volumeها از ArvanCloud ناموفق بود. اطلاعات قبلی، در صورت وجود، حفظ شده است.';
        }
    }

    /** @return list<array<string, bool|int|string|null>> */
    private function filteredItems(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(
            array_filter(
                $this->items,
                function (array $item) use ($search): bool {
                    if (
                        $this->filter !== 'all'
                        && ($item['audit_status'] ?? null) !== $this->filter
                    ) {
                        return false;
                    }

                    if ($search === '') {
                        return true;
                    }

                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $item['volume_id'] ?? null,
                        $item['volume_name'] ?? null,
                        $item['region_id'] ?? null,
                        $item['attachment_server_id'] ?? null,
                        $item['attachment_server_name'] ?? null,
                        $item['coreflare_server_id'] ?? null,
                        $item['coreflare_server_name'] ?? null,
                        $item['coreflare_provider_server_id'] ?? null,
                    ], static fn (mixed $value): bool => $value !== null)));

                    return str_contains($haystack, $search);
                },
            ),
        );
    }

    private function adminUser(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        return $user;
    }
}
