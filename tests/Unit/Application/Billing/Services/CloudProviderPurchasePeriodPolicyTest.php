<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing\Services;

use App\Application\Billing\Services\CloudProviderPurchasePeriodPolicy;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Tests\TestCase;

final class CloudProviderPurchasePeriodPolicyTest extends TestCase
{
    public function test_parspack_allows_seven_day_and_monthly_periods_only(): void
    {
        $policy = new CloudProviderPurchasePeriodPolicy();

        $this->assertSame(
            ['7_days', '1_month'],
            $policy->allowedPeriodIds(CloudProviderType::ParsPack),
        );
        $this->assertTrue(
            $policy->allows(CloudProviderType::ParsPack, '7_days'),
        );
        $this->assertTrue(
            $policy->allows(CloudProviderType::ParsPack, '1_month'),
        );
        $this->assertFalse(
            $policy->allows(CloudProviderType::ParsPack, '2_days'),
        );
        $this->assertFalse(
            $policy->allows(CloudProviderType::ParsPack, '14_days'),
        );
    }

    public function test_existing_providers_keep_their_existing_periods(): void
    {
        $policy = new CloudProviderPurchasePeriodPolicy();

        $expected = ['2_days', '14_days', '1_month'];

        $this->assertSame(
            $expected,
            $policy->allowedPeriodIds(CloudProviderType::Arvan),
        );
        $this->assertSame(
            $expected,
            $policy->allowedPeriodIds(CloudProviderType::Liara),
        );
    }

    public function test_provider_availability_policy_is_independent_from_pricing_catalog_shape(): void
    {
        config()->set(
            'money.periods',
            [
                '7_days' => [
                    'label' => '۷ روزه',
                    'hours' => 168,
                    'pricing' => 'hourly',
                ],
            ],
        );

        $policy = new CloudProviderPurchasePeriodPolicy();

        $this->assertSame(
            ['7_days', '1_month'],
            $policy->allowedPeriodIds(CloudProviderType::ParsPack),
        );
        $this->assertTrue(
            $policy->allows(CloudProviderType::ParsPack, '7_days'),
        );
    }

    public function test_disallowed_period_is_rejected_server_side(): void
    {
        $this->expectException(CloudValidationException::class);
        $this->expectExceptionMessage(
            'Purchase period [2_days] is not available for cloud provider [parspack].',
        );

        (new CloudProviderPurchasePeriodPolicy())->assertAllowed(
            provider: CloudProviderType::ParsPack,
            period: '2_days',
        );
    }
}
