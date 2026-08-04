<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cloud.default' => 'arvan',

            'cloud.providers.arvan.api_key' => 'test-api-key',

            'cloud.providers.arvan.base_url' => 'https://api.example.test/ecc/v1',

            'cloud.providers.arvan.timeouts.connect' => 5,

            'cloud.providers.arvan.timeouts.request' => 15,

            'cloud.providers.arvan.defaults.create_type' => 'cinder',

            'cloud.providers.arvan.defaults.username' => 'ubuntu',

            'cloud.provisioning.max_attempts' => 20,

            'cloud.provisioning.poll_delay_seconds' => 3,
        ]);

        $this->forgetCloudInstances();
    }

    public function test_it_resolves_cloud_contracts_to_the_same_provider(): void
    {
        $catalog = $this->app->make(
            CloudProviderInterface::class,
        );

        $provisioner = $this->app->make(
            CloudServerProvisionerInterface::class,
        );

        $this->assertInstanceOf(
            ArvanCloudProvider::class,
            $catalog,
        );

        $this->assertSame(
            $catalog,
            $provisioner,
        );
    }

    public function test_it_resolves_the_complete_provisioning_workflow(): void
    {
        $action = $this->app->make(
            ProvisionCloudServerAction::class,
        );

        $this->assertInstanceOf(
            ProvisionCloudServerAction::class,
            $action,
        );
    }

    public function test_it_rejects_invalid_provisioning_attempts(): void
    {
        config([
            'cloud.provisioning.max_attempts' => 0,
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'Cloud provisioning attempts must be greater than zero.',
        );

        $this->app->make(
            ProvisionCloudServerAction::class,
        );
    }

    public function test_it_rejects_a_negative_poll_delay(): void
    {
        config([
            'cloud.provisioning.poll_delay_seconds' => -1,
        ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->expectExceptionMessage(
            'Cloud provisioning poll delay cannot be negative.',
        );

        $this->app->make(
            ProvisionCloudServerAction::class,
        );
    }

    private function forgetCloudInstances(): void
    {
        $this->app->forgetInstance(
            ArvanCloudClient::class,
        );

        $this->app->forgetInstance(
            ArvanCloudProvider::class,
        );

        $this->app->forgetInstance(
            CloudProviderInterface::class,
        );

        $this->app->forgetInstance(
            CloudServerProvisionerInterface::class,
        );
    }
}
