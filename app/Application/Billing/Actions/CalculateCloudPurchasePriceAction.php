<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudPurchasePricingSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use Closure;
use InvalidArgumentException;

final readonly class CalculateCloudPurchasePriceAction
{
    public function __construct(
        private CloudPricingCalculator $calculator,
        private ?CloudProviderRegistryInterface $providers = null,
        private ?CloudProviderInterface $cloud = null,
        private ?CloudServerResizeCatalogInterface $pricing = null,
    ) {}

    public function execute(
        string $region,
        string $sizeId,
        int $selectedDiskGiB,
        string $period,
        CloudProviderType $provider = CloudProviderType::Arvan,
    ): PurchasePriceData {
        [
            $cloud,
            $pricing,
        ] = $this->providerDependencies(
            $provider,
        );

        return $this->calculate(
            size: $this->findSize(
                sizes: $cloud->listSizes(
                    $region,
                ),
                region: $region,
                sizeId: $sizeId,
            ),
            diskPrice: static fn (
                string $priceRegion,
                string $priceSizeId,
                int $diskGiB,
            ): CloudDiskPriceData => $pricing->calculateDiskPrice(
                region: $priceRegion,
                sizeId: $priceSizeId,
                diskGiB: $diskGiB,
            ),
            region: $region,
            sizeId: $sizeId,
            selectedDiskGiB: $selectedDiskGiB,
            period: $period,
        );
    }

    /**
     * @param  list<CloudSizeData>  $sizes
     */
    public function executeForPurchasePage(
        array $sizes,
        string $region,
        string $sizeId,
        int $selectedDiskGiB,
        string $period,
        CloudProviderType $provider = CloudProviderType::Arvan,
    ): PurchasePriceData {
        [$cloud, $pricing] = $this->providerDependencies(
            $provider,
        );

        $diskPrice = $cloud instanceof CloudPurchasePricingSourceInterface
            ? static fn (
                string $priceRegion,
                string $priceSizeId,
                int $diskGiB,
            ): CloudDiskPriceData => $cloud->calculatePurchaseDiskPrice(
                region: $priceRegion,
                sizeId: $priceSizeId,
                diskGiB: $diskGiB,
            )
            : static fn (
                string $priceRegion,
                string $priceSizeId,
                int $diskGiB,
            ): CloudDiskPriceData => $pricing->calculateDiskPrice(
                region: $priceRegion,
                sizeId: $priceSizeId,
                diskGiB: $diskGiB,
            );

        return $this->calculate(
            size: $this->findSize(
                sizes: $sizes,
                region: $region,
                sizeId: $sizeId,
            ),
            diskPrice: $diskPrice,
            region: $region,
            sizeId: $sizeId,
            selectedDiskGiB: $selectedDiskGiB,
            period: $period,
        );
    }

    /**
     * @param  list<CloudSizeData>  $sizes
     */
    private function findSize(
        array $sizes,
        string $region,
        string $sizeId,
    ): CloudSizeData {
        $size = null;

        foreach ($sizes as $candidate) {
            if ($candidate->id === $sizeId) {
                $size = $candidate;

                break;
            }
        }

        if ($size === null) {
            throw new InvalidArgumentException(
                "Cloud size [{$sizeId}] was not found in region [{$region}]."
            );
        }

        return $size;
    }

    private function calculate(
        CloudSizeData $size,
        Closure $diskPrice,
        string $region,
        string $sizeId,
        int $selectedDiskGiB,
        string $period,
    ): PurchasePriceData {
        $defaultDiskGiB = $size->diskGiB;

        if ($selectedDiskGiB < $defaultDiskGiB) {
            throw new InvalidArgumentException(
                "Selected disk cannot be smaller than the default {$defaultDiskGiB} GiB."
            );
        }

        /*
         * Default disk is already included in the catalog price.
         * Only the requested root-disk delta is added to the quote.
         */
        if ($selectedDiskGiB === $defaultDiskGiB) {
            $defaultDiskHourly = '0';
            $defaultDiskMonthly = '0';
            $selectedDiskHourly = '0';
            $selectedDiskMonthly = '0';
        } else {
            $defaultDiskPrice = $diskPrice(
                $region,
                $sizeId,
                $defaultDiskGiB,
            );

            $selectedDiskPrice = $diskPrice(
                $region,
                $sizeId,
                $selectedDiskGiB,
            );

            $defaultDiskHourly = $defaultDiskPrice->hourlyPrice->amount;
            $defaultDiskMonthly = $defaultDiskPrice->monthlyPrice->amount;

            $selectedDiskHourly = $selectedDiskPrice->hourlyPrice->amount;
            $selectedDiskMonthly = $selectedDiskPrice->monthlyPrice->amount;
        }

        $result = $this->calculator->calculate(
            baseHourlyPrice: $size->hourlyPrice->amount,
            baseMonthlyPrice: $size->monthlyPrice->amount,
            defaultDiskHourlyPrice: $defaultDiskHourly,
            defaultDiskMonthlyPrice: $defaultDiskMonthly,
            selectedDiskHourlyPrice: $selectedDiskHourly,
            selectedDiskMonthlyPrice: $selectedDiskMonthly,
            period: $period,
        );

        return new PurchasePriceData(
            regionId: $region,
            sizeId: $sizeId,
            defaultDiskGiB: $defaultDiskGiB,
            selectedDiskGiB: $selectedDiskGiB,
            period: $result['period'],
            durationHours: $result['duration_hours'],
            providerCost: $result['provider_cost'],
            markupPercent: $result['markup_percent'],
            finalAmount: $result['final_amount'],
            currency: $result['currency'],
        );
    }

    /**
     * @return array{0: CloudProviderInterface, 1: CloudServerResizeCatalogInterface}
     */
    private function providerDependencies(
        CloudProviderType $provider,
    ): array {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            $cloud = $this->providers->resolve(
                $provider,
            );

            $pricing = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerResizeCatalogInterface::class,
            );

            if (! $pricing instanceof CloudServerResizeCatalogInterface) {
                throw new CloudConfigurationException(
                    sprintf(
                        'The cloud provider [%s] has no resize pricing capability.',
                        $provider->value,
                    ),
                );
            }

            return [
                $cloud,
                $pricing,
            ];
        }

        /*
         * Transitional direct-construction seam for existing unit/feature
         * tests. Production container resolution supplies the registry and
         * therefore always routes by the explicit provider identity.
         */
        if (
            $provider !== CloudProviderType::Arvan
            || ! $this->cloud instanceof CloudProviderInterface
            || ! $this->pricing instanceof CloudServerResizeCatalogInterface
        ) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud pricing dependencies for provider [%s] are not configured.',
                    $provider->value,
                ),
            );
        }

        return [
            $this->cloud,
            $this->pricing,
        ];
    }
}
