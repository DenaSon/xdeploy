<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Billing\Actions\CreatePaymentAction;
use App\Application\Cloud\Actions\ListSupportedCloudImagesAction;
use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

#[Title('خرید سرور')]
final class Buy extends Component
{
    use Toast;

    /**
     * @var list<array{
     *     id: string,
     *     name: string,
     *     country: string|null,
     *     city: string|null,
     *     data_center: string|null
     * }>
     */
    public array $regions = [];

    /**
     * @var list<array{
     *     id: string,
     *     name: string,
     *     category: string|null,
     *     vcpu: int,
     *     memory_mib: int,
     *     memory_label: string,
     *     disk_gib: int
     * }>
     */
    public array $sizes = [];

    /**
     * @var list<array{
     *     id: string,
     *     name: string,
     *     distribution: string,
     *     version: string,
     *     min_disk_gib: int|null,
     *     min_memory_mib: int|null
     * }>
     */
    public array $images = [];

    /**
     * @var list<array{id: string, label: string}>
     */
    public array $periods = [];

    /**
     * @var array{
     *     final_amount: int,
     *     currency: string,
     *     duration_hours: int,
     *     selected_disk_gib: int
     * }|array{}
     */
    public array $quote = [];

    public string $regionId = '';

    public string $sizeId = '';

    public string $imageId = '';

    public string $period = '';

    public int $selectedDiskGiB = 0;

    public ?int $pendingOrderId = null;

    public ?string $catalogError = null;

    public ?string $quoteError = null;

    public function mount(): void
    {
        $this->loadPeriods();

        $this->loadRegions();
    }

    public function reloadCatalog(): void
    {
        $this->catalogError = null;
        $this->quoteError = null;
        $this->pendingOrderId = null;

        $this->loadRegions();
    }

    public function selectRegion(
        string $regionId,
    ): void {
        if (
            $this->findById(
                $this->regions,
                $regionId,
            ) === null
        ) {
            return;
        }

        $this->pendingOrderId = null;

        $this->loadRegionCatalog(
            $regionId,
        );
    }

    public function selectSize(
        string $sizeId,
    ): void {
        $size = $this->findById(
            $this->sizes,
            $sizeId,
        );

        if ($size === null) {
            return;
        }

        $this->sizeId = $sizeId;
        $this->selectedDiskGiB =
            $this->minimumDiskGiB();

        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function selectImage(
        string $imageId,
    ): void {
        if (
            $this->findById(
                $this->images,
                $imageId,
            ) === null
        ) {
            return;
        }

        $this->imageId = $imageId;
        $this->selectedDiskGiB = max(
            $this->selectedDiskGiB,
            $this->minimumDiskGiB(),
        );

        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function selectPeriod(
        string $period,
    ): void {
        if (
            $this->findById(
                $this->periods,
                $period,
            ) === null
        ) {
            return;
        }

        $this->period = $period;
        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function increaseDisk(): void
    {
        if ($this->sizeId === '') {
            return;
        }

        $this->selectedDiskGiB += 10;
        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function decreaseDisk(): void
    {
        if ($this->sizeId === '') {
            return;
        }

        $this->selectedDiskGiB = max(
            $this->minimumDiskGiB(),
            $this->selectedDiskGiB - 10,
        );

        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function purchase(
        CreateOrderAction $createOrder,
        CreatePaymentAction $createPayment,
    ): mixed {
        if (! $this->selectionIsValid()) {
            $this->error(
                'اطلاعات خرید کامل نیست',
                'منطقه، پلن، سیستم‌عامل و دوره پرداخت را انتخاب کنید.',
            );

            return null;
        }

        if ($this->quote === []) {
            $this->recalculateQuote();

            if ($this->quote === []) {
                $this->error(
                    'قیمت در دسترس نیست',
                    'در حال حاضر امکان دریافت قیمت این پیکربندی وجود ندارد.',
                );

                return null;
            }
        }

        $user = $this->authenticatedUser();

        try {
            /*
             * Keep the same pending Order when gateway initiation fails.
             * A retry should not create duplicate commercial Orders.
             */
            if ($this->pendingOrderId === null) {
                $order = $createOrder->execute(
                    user: $user,
                    region: $this->regionId,
                    sizeId: $this->sizeId,
                    imageId: $this->imageId,
                    selectedDiskGiB: $this->selectedDiskGiB,
                    period: $this->period,
                );

                $this->pendingOrderId =
                    $order->getKey();
            }

            $payment = $createPayment->execute(
                user: $user,
                orderId: $this->pendingOrderId,
                callbackUrl: route(
                    'payments.zarinpal.callback',
                ),
            );

            return redirect()->away(
                $payment->redirectUrl,
            );
        } catch (OrderQuoteExpiredException) {
            $this->pendingOrderId = null;
            $this->recalculateQuote();

            $this->warning(
                'پیش‌فاکتور منقضی شد',
                'قیمت به‌روزرسانی شد. دوباره پرداخت را شروع کنید.',
            );

            return null;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->error(
                'شروع پرداخت ناموفق بود',
                'سفارش حفظ شد. چند لحظه دیگر دوباره تلاش کنید.',
            );

            return null;
        }
    }

    public function render(): View
    {
        return view(
            'livewire.servers.buy',
            [
                'selectedRegion' => $this->findById(
                    $this->regions,
                    $this->regionId,
                ),

                'selectedSize' => $this->findById(
                    $this->sizes,
                    $this->sizeId,
                ),

                'selectedImage' => $this->findById(
                    $this->images,
                    $this->imageId,
                ),

                'selectedPeriod' => $this->findById(
                    $this->periods,
                    $this->period,
                ),

                'minimumDiskGiB' => $this->minimumDiskGiB(),
            ],
        )->layout(
            'layouts.panel',
        );
    }

    private function loadPeriods(): void
    {
        $configuredPeriods = (array) config(
            'money.periods',
            [],
        );

        $this->periods = [];

        foreach (
            $configuredPeriods as $id => $config
        ) {
            if (
                ! is_string($id)
                || ! is_array($config)
            ) {
                continue;
            }

            $label = $config['label'] ?? null;

            if (! is_string($label)) {
                continue;
            }

            $this->periods[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        $preferred = $this->findById(
            $this->periods,
            '1_month',
        );

        $this->period =
            $preferred['id']
            ?? ($this->periods[0]['id'] ?? '');
    }

    private function loadRegions(): void
    {
        try {
            $cloud = app(
                CloudProviderInterface::class,
            );

            $regions = array_values(
                array_filter(
                    $cloud->listRegions(),
                    static fn (
                        CloudRegionData $region,
                    ): bool => $region->canCreateServers
                        && $region->isVisible,
                ),
            );

            $this->regions = array_map(
                static fn (
                    CloudRegionData $region,
                ): array => [
                    'id' => $region->id,
                    'name' => $region->displayName
                        ?? $region->id,
                    'country' => $region->country,
                    'city' => $region->city,
                    'data_center' => $region->dataCenter,
                ],
                $regions,
            );

            if ($this->regions === []) {
                $this->catalogError =
                    'در حال حاضر هیچ منطقه‌ای برای ساخت سرور در دسترس نیست.';

                return;
            }

            $this->loadRegionCatalog(
                $this->regions[0]['id'],
            );
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->regions = [];
            $this->sizes = [];
            $this->images = [];
            $this->quote = [];

            $this->catalogError =
                'دریافت اطلاعات سرورهای ابری ناموفق بود. دوباره تلاش کنید.';
        }
    }

    private function loadRegionCatalog(
        string $regionId,
    ): void {
        $this->catalogError = null;
        $this->quoteError = null;

        $this->regionId = $regionId;
        $this->sizeId = '';
        $this->imageId = '';
        $this->selectedDiskGiB = 0;
        $this->sizes = [];
        $this->images = [];
        $this->quote = [];

        try {
            $cloud = app(
                CloudProviderInterface::class,
            );

            /*
             * CloudSizeData prices are nullable at the Domain boundary.
             * Never expose an unpriced size to the commercial UI.
             */
            $purchasableSizes = array_values(
                array_filter(
                    $cloud->listSizes(
                        $regionId,
                    ),
                    static fn (
                        CloudSizeData $size,
                    ): bool => $size->hourlyPrice !== null
                        && $size->monthlyPrice !== null,
                ),
            );

            usort(
                $purchasableSizes,
                static fn (
                    CloudSizeData $left,
                    CloudSizeData $right,
                ): int => (int) $left->monthlyPrice->amount
                    <=>
                    (int) $right->monthlyPrice->amount,
            );

            $this->sizes = array_map(
                fn (
                    CloudSizeData $size,
                ): array => [
                    'id' => $size->id,
                    'name' => $size->name,
                    'category' => $size->category,
                    'vcpu' => $size->vCpu,
                    'memory_mib' => $size->memoryMiB,
                    'memory_label' => $this->memoryLabel(
                        $size->memoryMiB,
                    ),
                    'disk_gib' => $size->diskGiB,
                ],
                $purchasableSizes,
            );

            $supportedImages = app(
                ListSupportedCloudImagesAction::class,
            )->execute(
                $regionId,
            );

            $this->images = array_map(
                static fn (
                    CloudImageData $image,
                ): array => [
                    'id' => $image->id,
                    'name' => $image->name,
                    'distribution' => $image->distribution,
                    'version' => $image->version,
                    'min_disk_gib' => $image->minDiskGiB,
                    'min_memory_mib' => $image->minMemoryMiB,
                ],
                $supportedImages,
            );

            if ($this->sizes === []) {
                $this->catalogError =
                    'در این منطقه پلن قابل خریدی در دسترس نیست.';

                return;
            }

            if ($this->images === []) {
                $this->catalogError =
                    'در این منطقه سیستم‌عامل سازگار با xDeploy در دسترس نیست.';

                return;
            }

            $this->sizeId =
                $this->sizes[0]['id'];

            $this->imageId =
                $this->images[0]['id'];

            $this->selectedDiskGiB =
                $this->minimumDiskGiB();

            $this->recalculateQuote();
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->sizes = [];
            $this->images = [];
            $this->quote = [];

            $this->catalogError =
                'دریافت پلن‌های این منطقه ناموفق بود. منطقه دیگری انتخاب کنید یا دوباره تلاش کنید.';
        }
    }

    private function recalculateQuote(): void
    {
        $this->quote = [];
        $this->quoteError = null;

        if (! $this->selectionIsValid()) {
            return;
        }

        try {
            $price = app(
                CalculateCloudPurchasePriceAction::class,
            )->execute(
                region: $this->regionId,
                sizeId: $this->sizeId,
                selectedDiskGiB: $this->selectedDiskGiB,
                period: $this->period,
            );

            $this->quote =
                $this->quoteArray(
                    $price,
                );
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->quoteError =
                'محاسبه قیمت این پیکربندی ناموفق بود.';
        }
    }

    /**
     * @return array{
     *     final_amount: int,
     *     currency: string,
     *     duration_hours: int,
     *     selected_disk_gib: int
     * }
     */
    private function quoteArray(
        PurchasePriceData $price,
    ): array {
        return [
            'final_amount' => (int) $price->finalAmount,

            'currency' => $price->currency,

            'duration_hours' => $price->durationHours,

            'selected_disk_gib' => $price->selectedDiskGiB,
        ];
    }

    private function minimumDiskGiB(): int
    {
        $size = $this->findById(
            $this->sizes,
            $this->sizeId,
        );

        $image = $this->findById(
            $this->images,
            $this->imageId,
        );

        return max(
            (int) ($size['disk_gib'] ?? 0),
            (int) ($image['min_disk_gib'] ?? 0),
        );
    }

    private function selectionIsValid(): bool
    {
        return
            $this->findById(
                $this->regions,
                $this->regionId,
            ) !== null
            && $this->findById(
                $this->sizes,
                $this->sizeId,
            ) !== null
            && $this->findById(
                $this->images,
                $this->imageId,
            ) !== null
            && $this->findById(
                $this->periods,
                $this->period,
            ) !== null
            && $this->selectedDiskGiB
                >= $this->minimumDiskGiB();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function findById(
        array $items,
        string $id,
    ): ?array {
        foreach ($items as $item) {
            if (
                ($item['id'] ?? null)
                === $id
            ) {
                return $item;
            }
        }

        return null;
    }

    private function memoryLabel(
        int $memoryMiB,
    ): string {
        if (
            $memoryMiB > 0
            && $memoryMiB % 1024 === 0
        ) {
            return sprintf(
                '%d GB',
                intdiv(
                    $memoryMiB,
                    1024,
                ),
            );
        }

        return sprintf(
            '%d MB',
            $memoryMiB,
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
