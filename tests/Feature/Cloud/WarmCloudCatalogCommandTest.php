<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Catalog\CloudCatalogReaderResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class WarmCloudCatalogCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cloud.catalog_cache.enabled', true);

        Cache::flush();
    }

    public function test_it_warms_every_purchasable_provider_with_isolated_cache_keys(): void
    {
        [$arvan, $liara] = $this->bindCatalogProviders([
            CloudProviderType::Arvan,
            CloudProviderType::Liara,
        ]);

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('cloud:catalog:warm'),
        );

        $this->assertSame(1, $arvan->regionCalls);
        $this->assertSame(1, $arvan->sizeCalls);
        $this->assertSame(1, $arvan->imageCalls);
        $this->assertSame(1, $liara->regionCalls);
        $this->assertSame(1, $liara->sizeCalls);
        $this->assertSame(1, $liara->imageCalls);

        $this->assertStringContainsString(
            'Warmed arvan:arvan-region',
            Artisan::output(),
        );
        $this->assertStringContainsString(
            'Warmed liara:liara-region',
            Artisan::output(),
        );
    }

    public function test_force_refreshes_provider_scoped_catalog_entries(): void
    {
        [$arvan, $liara] = $this->bindCatalogProviders([
            CloudProviderType::Arvan,
            CloudProviderType::Liara,
        ]);

        Artisan::call('cloud:catalog:warm');
        Artisan::call('cloud:catalog:warm');

        $this->assertSame(1, $arvan->regionCalls);
        $this->assertSame(1, $arvan->sizeCalls);
        $this->assertSame(1, $arvan->imageCalls);
        $this->assertSame(1, $liara->regionCalls);
        $this->assertSame(1, $liara->sizeCalls);
        $this->assertSame(1, $liara->imageCalls);

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call(
                'cloud:catalog:warm',
                ['--force' => true],
            ),
        );

        $this->assertSame(2, $arvan->regionCalls);
        $this->assertSame(2, $arvan->sizeCalls);
        $this->assertSame(2, $arvan->imageCalls);
        $this->assertSame(2, $liara->regionCalls);
        $this->assertSame(2, $liara->sizeCalls);
        $this->assertSame(2, $liara->imageCalls);
    }

    public function test_provider_option_warms_only_the_selected_purchasable_provider(): void
    {
        [$arvan, $liara] = $this->bindCatalogProviders([
            CloudProviderType::Arvan,
            CloudProviderType::Liara,
        ]);

        $exitCode = Artisan::call(
            'cloud:catalog:warm',
            ['--provider' => 'liara'],
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, $arvan->regionCalls);
        $this->assertSame(0, $arvan->sizeCalls);
        $this->assertSame(0, $arvan->imageCalls);
        $this->assertSame(1, $liara->regionCalls);
        $this->assertSame(1, $liara->sizeCalls);
        $this->assertSame(1, $liara->imageCalls);
    }

    public function test_provider_option_rejects_an_operational_provider_that_is_not_purchasable(): void
    {
        [$arvan, $liara] = $this->bindCatalogProviders([
            CloudProviderType::Arvan,
        ]);

        $exitCode = Artisan::call(
            'cloud:catalog:warm',
            ['--provider' => 'liara'],
        );

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertSame(0, $arvan->regionCalls);
        $this->assertSame(0, $liara->regionCalls);
        $this->assertStringContainsString(
            'not enabled for purchases',
            Artisan::output(),
        );
    }

    public function test_one_provider_failure_does_not_prevent_other_catalogs_from_warming(): void
    {
        [$arvan, $liara] = $this->bindCatalogProviders(
            purchasable: [
                CloudProviderType::Arvan,
                CloudProviderType::Liara,
            ],
            arvanFails: true,
        );

        $exitCode = Artisan::call('cloud:catalog:warm');

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertSame(1, $arvan->regionCalls);
        $this->assertSame(0, $arvan->sizeCalls);
        $this->assertSame(0, $arvan->imageCalls);
        $this->assertSame(1, $liara->regionCalls);
        $this->assertSame(1, $liara->sizeCalls);
        $this->assertSame(1, $liara->imageCalls);
        $this->assertStringContainsString(
            'Failed to load catalog regions for provider [arvan].',
            Artisan::output(),
        );
        $this->assertStringContainsString(
            'Warmed liara:liara-region',
            Artisan::output(),
        );
    }

    /**
     * @param  list<CloudProviderType>  $purchasable
     * @return array{0: WarmCloudCatalogFakeProvider, 1: WarmCloudCatalogFakeProvider}
     */
    private function bindCatalogProviders(
        array $purchasable,
        bool $arvanFails = false,
    ): array {
        $arvan = new WarmCloudCatalogFakeProvider(
            regionId: 'arvan-region',
            failRegions: $arvanFails,
        );
        $liara = new WarmCloudCatalogFakeProvider(
            regionId: 'liara-region',
        );

        $registry = new WarmCloudCatalogFakeRegistry(
            providers: [
                CloudProviderType::Arvan->value => $arvan,
                CloudProviderType::Liara->value => $liara,
            ],
            purchasable: $purchasable,
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            $registry,
        );
        $this->app->instance(
            CloudCatalogReaderResolver::class,
            new CloudCatalogReaderResolver($registry),
        );

        return [$arvan, $liara];
    }
}

final class WarmCloudCatalogFakeProvider implements CloudProviderInterface
{
    public int $regionCalls = 0;

    public int $sizeCalls = 0;

    public int $imageCalls = 0;

    public function __construct(
        private readonly string $regionId,
        private readonly bool $failRegions = false,
    ) {}

    public function listRegions(): array
    {
        $this->regionCalls++;

        if ($this->failRegions) {
            throw new RuntimeException('Synthetic provider failure.');
        }

        return [
            new CloudRegionData(
                id: $this->regionId,
                displayName: $this->regionId,
                country: null,
                city: null,
                dataCenter: null,
                canCreateServers: true,
                isVisible: true,
                supportsVolumeBacked: false,
            ),
        ];
    }

    public function listSizes(string $region): array
    {
        $this->sizeCalls++;

        return [];
    }

    public function listImages(string $region): array
    {
        $this->imageCalls++;

        return [];
    }
}

final readonly class WarmCloudCatalogFakeRegistry implements CloudProviderRegistryInterface
{
    /**
     * @param  array<string, CloudProviderInterface>  $providers
     * @param  list<CloudProviderType>  $purchasable
     */
    public function __construct(
        private array $providers,
        private array $purchasable,
    ) {}

    public function registeredProviders(): array
    {
        return array_map(
            static fn (string $provider): CloudProviderType => CloudProviderType::from($provider),
            array_keys($this->providers),
        );
    }

    public function purchasableProviders(): array
    {
        return $this->purchasable;
    }

    public function resolve(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        return $this->providers[$provider->value]
            ?? throw new LogicException(
                sprintf('Provider [%s] is not registered.', $provider->value),
            );
    }

    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        throw new LogicException(
            sprintf(
                'Capability [%s] is not available for provider [%s].',
                $capability,
                $provider->value,
            ),
        );
    }

    public function supportsCapability(
        CloudProviderType $provider,
        string $capability,
    ): bool {
        return false;
    }
}
