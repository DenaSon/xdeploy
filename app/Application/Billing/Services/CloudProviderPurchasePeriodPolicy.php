<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudValidationException;

final readonly class CloudProviderPurchasePeriodPolicy
{
    /**
     * @return list<string>
     */
    public function allowedPeriodIds(
        CloudProviderType $provider,
    ): array {
        $configured = config(
            sprintf(
                'cloud_purchase.periods.%s',
                $provider->value,
            ),
        );

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] purchase periods are not configured.',
                    $provider->value,
                ),
            );
        }

        $periods = [];

        foreach ($configured as $period) {
            if (! is_string($period) || trim($period) === '') {
                throw new CloudConfigurationException(
                    sprintf(
                        'Cloud provider [%s] purchase period configuration is invalid.',
                        $provider->value,
                    ),
                );
            }

            $period = trim($period);
            $periods[$period] = $period;
        }

        if ($periods === []) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] has no allowed purchase periods.',
                    $provider->value,
                ),
            );
        }

        return array_values($periods);
    }

    public function allows(
        CloudProviderType $provider,
        string $period,
    ): bool {
        return in_array(
            trim($period),
            $this->allowedPeriodIds($provider),
            true,
        );
    }

    public function assertAllowed(
        CloudProviderType $provider,
        string $period,
    ): void {
        if ($this->allows($provider, $period)) {
            return;
        }

        throw new CloudValidationException(
            sprintf(
                'Purchase period [%s] is not available for cloud provider [%s].',
                trim($period),
                $provider->value,
            ),
        );
    }
}
