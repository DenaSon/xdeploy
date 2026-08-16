<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Billing\Actions\CreatePaymentAction;
use App\Application\Cloud\Actions\FilterSupportedCloudImagesAction;
use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudCatalogReaderResolverInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Models\User;
use App\Support\Cloud\CloudRegionLabel;
use App\Support\Money\MoneyFormatter;
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

    private const string REGION_GROUP_IRAN = 'iran';

    private const int MAX_VISIBLE_PLANS = 10;

    private const string REGION_GROUP_INTERNATIONAL = 'international';

    /**
     * @var list<array{
     *     id: string,
     *     label: string,
     *     description: string
     * }>
     */
    public array $providers = [];

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
     * @var list<array{
     *     id: string,
     *     label: string,
     *     hint: string,
     *     recommended: bool
     * }>
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

    public bool $catalogLoaded = false;

    public string $provider = '';

    public string $regionGroup = self::REGION_GROUP_IRAN;

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
        /*
         * Provider discovery is local container state only. No provider API
         * call happens during the first render; catalog I/O remains deferred
         * until wire:init.
         */
        $this->loadProviders();
        $this->loadPeriods();
    }

    public function regionDisplayName(
        array $region,
    ): string {
        $regionId = $region['id'] ?? null;

        if (
            is_string($regionId)
            && $regionId !== ''
        ) {
            $label = CloudRegionLabel::for(
                $regionId,
            );

            if ($label !== $regionId) {
                return $label;
            }
        }

        return $region['city']
            ?? $region['country']
            ?? $region['name']
            ?? $regionId
            ?? '—';
    }

    public function loadCatalog(): void
    {
        if ($this->catalogLoaded) {
            return;
        }

        $this->fetchCatalog();
    }

    public function reloadCatalog(): void
    {
        $this->catalogLoaded = false;
        $this->catalogError = null;
        $this->quoteError = null;
        $this->pendingOrderId = null;

        $this->fetchCatalog();
    }

    public function selectProvider(string $provider): void
    {
        if (
            $this->findById(
                $this->providers,
                $provider,
            ) === null
        ) {
            return;
        }

        if ($this->provider === $provider) {
            return;
        }

        $this->provider = $provider;
        $this->pendingOrderId = null;
        $this->catalogLoaded = false;
        $this->resetCatalogSelection();

        $this->fetchCatalog();
    }

    public function selectRegionGroup(string $group): void
    {
        if (! in_array(
            $group,
            [
                self::REGION_GROUP_IRAN,
                self::REGION_GROUP_INTERNATIONAL,
            ],
            true,
        )) {
            return;
        }

        $groupRegions = $this->regionsForGroup(
            $group,
        );

        if ($groupRegions === []) {
            return;
        }

        $this->regionGroup = $group;

        $selectedRegion = $this->findById(
            $this->regions,
            $this->regionId,
        );

        if (
            $selectedRegion !== null
            && $this->regionGroupFor(
                $selectedRegion,
            ) === $group
        ) {
            return;
        }

        $this->pendingOrderId = null;

        $this->loadRegionCatalog(
            $groupRegions[0]['id'],
        );
    }

    public function selectRegion(string $regionId): void
    {
        $region = $this->findById(
            $this->regions,
            $regionId,
        );

        if ($region === null) {
            return;
        }

        if ($this->regionId === $regionId) {
            return;
        }

        $this->regionGroup =
            $this->regionGroupFor(
                $region,
            );

        $this->pendingOrderId = null;

        $this->loadRegionCatalog(
            $regionId,
        );
    }

    public function selectSize(string $sizeId): void
    {
        $size = $this->findById(
            $this->sizes,
            $sizeId,
        );

        if ($size === null) {
            return;
        }

        if ($this->sizeId === $sizeId) {
            return;
        }

        $this->sizeId = $sizeId;

        $this->selectedDiskGiB =
            $this->minimumDiskGiB();

        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function selectImage(string $imageId): void
    {
        if (
            $this->findById(
                $this->images,
                $imageId,
            ) === null
        ) {
            return;
        }

        if ($this->imageId === $imageId) {
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

    public function updatedImageId(string $imageId): void
    {
        /*
         * Mary UI x-group updates imageId through wire:model.live.
         * Recalculate the quote after Livewire has applied the new value.
         */
        if (
            $this->findById(
                $this->images,
                $imageId,
            ) === null
        ) {
            return;
        }

        $this->selectedDiskGiB = max(
            $this->selectedDiskGiB,
            $this->minimumDiskGiB(),
        );

        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function selectPeriod(string $period): void
    {
        if (
            $this->findById(
                $this->periods,
                $period,
            ) === null
        ) {
            return;
        }

        if ($this->period === $period) {
            return;
        }

        $this->period = $period;
        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function increaseDisk(): void
    {
        if (
            $this->sizeId === ''
            || ! $this->customDiskEnabled()
        ) {
            return;
        }

        $this->selectedDiskGiB += 10;
        $this->pendingOrderId = null;

        $this->recalculateQuote();
    }

    public function decreaseDisk(): void
    {
        if (
            $this->sizeId === ''
            || ! $this->customDiskEnabled()
        ) {
            return;
        }

        $nextValue = max(
            $this->minimumDiskGiB(),
            $this->selectedDiskGiB - 10,
        );

        if ($nextValue === $this->selectedDiskGiB) {
            return;
        }

        $this->selectedDiskGiB = $nextValue;
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
                'لطفاً ارائه‌دهنده، منطقه، پلن، سیستم‌عامل و دوره پرداخت را انتخاب کنید.',
            );

            return null;
        }

        if ($this->quote === []) {
            $this->recalculateQuote();

            if ($this->quote === []) {
                $this->error(
                    'قیمت در دسترس نیست',
                    'در حال حاضر امکان دریافت قیمت این پیکربندی وجود ندارد. لطفاً دوباره تلاش کنید.',
                );

                return null;
            }
        }

        $user = $this->authenticatedUser();
        $provider = $this->providerType();

        try {
            if ($this->pendingOrderId === null) {
                $order = $createOrder->execute(
                    user: $user,
                    region: $this->regionId,
                    sizeId: $this->sizeId,
                    imageId: $this->imageId,
                    selectedDiskGiB: $this->selectedDiskGiB,
                    period: $this->period,
                    provider: $provider,
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
                'قیمت سفارش به‌روزرسانی شد. لطفاً پرداخت را دوباره آغاز کنید.',
            );

            return null;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->error(
                'شروع پرداخت ناموفق بود',
                'سفارش شما حفظ شده است. لطفاً چند لحظه دیگر دوباره تلاش کنید.',
            );

            return null;
        }
    }

    public function render(): View
    {
        return view(
            'livewire.servers.buy-page',
            [
                'visibleRegions' => $this->regionsForGroup(
                    $this->regionGroup,
                ),

                'regionGroupCounts' => [
                    self::REGION_GROUP_IRAN => count(
                        $this->regionsForGroup(
                            self::REGION_GROUP_IRAN,
                        ),
                    ),

                    self::REGION_GROUP_INTERNATIONAL => count(
                        $this->regionsForGroup(
                            self::REGION_GROUP_INTERNATIONAL,
                        ),
                    ),
                ],

                'selectedProvider' => $this->findById(
                    $this->providers,
                    $this->provider,
                ),

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

                'customDiskEnabled' => $this->customDiskEnabled(),

                'providerLabel' => $this->providerLabel(),

                'quoteTtlMinutes' => max(
                    1,
                    (int) config(
                        'money.quote_ttl_minutes',
                        15,
                    ),
                ),
            ],
        )->layout(
            'layouts.panel',
        );
    }

    private function fetchCatalog(): void
    {
        $this->catalogError = null;
        $this->quoteError = null;

        if ($this->provider === '') {
            $this->catalogLoaded = true;
            $this->catalogError =
                'در حال حاضر هیچ ارائه‌دهنده ابری برای خرید فعال نیست.';

            return;
        }

        try {
            $catalog = $this->catalog();

            $regions = array_values(
                array_filter(
                    $catalog->listRegions(),
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
                $this->catalogLoaded = true;
                $this->catalogError =
                    'در حال حاضر هیچ منطقه‌ای برای ساخت سرور در دسترس نیست.';

                return;
            }

            $iranRegions = $this->regionsForGroup(
                self::REGION_GROUP_IRAN,
            );

            $preferredRegion =
                $iranRegions[0]
                ?? $this->regions[0];

            $this->regionGroup =
                $this->regionGroupFor(
                    $preferredRegion,
                );

            $this->loadRegionCatalog(
                $preferredRegion['id'],
            );

            $this->catalogLoaded = true;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->resetCatalogSelection();
            $this->catalogLoaded = true;

            $this->catalogError =
                'دریافت اطلاعات سرورهای ابری ناموفق بود. دوباره تلاش کنید.';
        }
    }

    private function loadProviders(): void
    {
        $registered = app(
            CloudProviderRegistryInterface::class,
        )->registeredProviders();

        $this->providers = array_map(
            fn (CloudProviderType $provider): array => [
                'id' => $provider->value,
                'label' => $this->providerLabelFor($provider),
                'description' => $this->providerDescriptionFor($provider),
            ],
            $registered,
        );

        $configuredDefault = CloudProviderType::tryFrom(
            strtolower(
                trim(
                    (string) config(
                        'cloud.default',
                        '',
                    ),
                ),
            ),
        );

        if (
            $configuredDefault instanceof CloudProviderType
            && $this->findById(
                $this->providers,
                $configuredDefault->value,
            ) !== null
        ) {
            $this->provider = $configuredDefault->value;

            return;
        }

        $this->provider = $this->providers[0]['id'] ?? '';
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
                'hint' => $this->periodHint(
                    $id,
                ),
                'recommended' => $id === '14_days',
            ];
        }

        $preferred = $this->findById(
            $this->periods,
            '14_days',
        );

        $this->period =
            $preferred['id']
            ?? ($this->periods[0]['id'] ?? '');
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
            $catalog = $this->catalog();

            $purchasableSizes = array_values(
                array_filter(
                    $catalog->listSizes(
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

            $purchasableSizes = array_slice(
                $purchasableSizes,
                0,
                self::MAX_VISIBLE_PLANS,
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
                FilterSupportedCloudImagesAction::class,
            )->execute(
                $catalog->listImages(
                    $regionId,
                ),
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
                    'در این منطقه سیستم‌عامل سازگار با Coreflare در دسترس نیست.';

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
                'دریافت پلن‌های این منطقه ناموفق بود. لطفاً منطقه دیگری را انتخاب کنید یا دوباره تلاش کنید.';
        }
    }

    public function formatToman(
        int $rialAmount,
    ): string {
        return MoneyFormatter::tomanFromRial(
            $rialAmount,
        );
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
                provider: $this->providerType(),
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
                $this->providers,
                $this->provider,
            ) !== null
            && $this->findById(
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

    private function catalog(): CloudCatalogReaderInterface
    {
        return app(
            CloudCatalogReaderResolverInterface::class,
        )->resolve(
            $this->providerType(),
        );
    }

    private function providerType(): CloudProviderType
    {
        $provider = CloudProviderType::tryFrom(
            strtolower(
                trim(
                    $this->provider,
                ),
            ),
        );

        if (! $provider instanceof CloudProviderType) {
            throw new CloudConfigurationException(
                sprintf(
                    'Selected cloud provider [%s] is invalid.',
                    $this->provider,
                ),
            );
        }

        return $provider;
    }

    private function resetCatalogSelection(): void
    {
        $this->regions = [];
        $this->sizes = [];
        $this->images = [];
        $this->quote = [];
        $this->regionGroup = self::REGION_GROUP_IRAN;
        $this->regionId = '';
        $this->sizeId = '';
        $this->imageId = '';
        $this->selectedDiskGiB = 0;
        $this->catalogError = null;
        $this->quoteError = null;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function regionsForGroup(
        string $group,
    ): array {
        return array_values(
            array_filter(
                $this->regions,
                fn (array $region): bool => $this->regionGroupFor(
                    $region,
                ) === $group,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $region
     */
    private function regionGroupFor(
        array $region,
    ): string {
        $country = mb_strtolower(
            trim(
                (string) ($region['country'] ?? ''),
            ),
        );

        $name = mb_strtolower(
            trim(
                (string) ($region['name'] ?? ''),
            ),
        );

        $id = mb_strtolower(
            trim(
                (string) ($region['id'] ?? ''),
            ),
        );

        if (
            $id === 'iran'
            || str_starts_with(
                $id,
                'ir-',
            )
            || $country === 'ir'
            || str_contains(
                $country,
                'iran',
            )
            || str_contains(
                $country,
                'ایران',
            )
            || str_contains(
                $name,
                'iran',
            )
            || str_contains(
                $name,
                'ایران',
            )
        ) {
            return self::REGION_GROUP_IRAN;
        }

        return self::REGION_GROUP_INTERNATIONAL;
    }

    private function periodHint(
        string $period,
    ): string {
        return match ($period) {
            '2_days' => 'مناسب آزمایش کوتاه',

            '14_days' => 'مناسب استفاده کوتاه',

            '1_month' => 'مناسب استفاده ماهانه',

            default => 'دوره استفاده',
        };
    }

    private function providerLabel(): string
    {
        $provider = CloudProviderType::tryFrom(
            $this->provider,
        );

        return $provider instanceof CloudProviderType
            ? $this->providerLabelFor($provider)
            : 'ارائه‌دهنده ابری';
    }

    private function providerLabelFor(
        CloudProviderType $provider,
    ): string {
        return match ($provider) {
            CloudProviderType::Arvan => 'ابر آروان',
            CloudProviderType::Liara => 'لیارا',
        };
    }

    private function providerDescriptionFor(
        CloudProviderType $provider,
    ): string {
        return match ($provider) {
            CloudProviderType::Arvan => 'زیرساخت ابری آروان',
            CloudProviderType::Liara => 'سرور ابری لیارا',
        };
    }

    private function customDiskEnabled(): bool
    {
        return CloudProviderType::tryFrom(
            $this->provider,
        ) === CloudProviderType::Arvan;
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
