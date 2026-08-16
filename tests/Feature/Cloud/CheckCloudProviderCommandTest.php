<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

final class CheckCloudProviderCommandTest extends TestCase
{
    private const BASE_URL = 'https://api.example.test/ecc/v1';

    private const API_KEY = 'private-test-api-key';

    private const LIARA_BASE_URL = 'https://iaas-api.example.test';

    private const LIARA_TOKEN = 'private-test-liara-token';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set('cloud.default', 'arvan');

        config()->set(
            'cloud.providers.arvan.base_url',
            self::BASE_URL,
        );

        config()->set(
            'cloud.providers.arvan.api_key',
            self::API_KEY,
        );

        config()->set(
            'cloud.providers.arvan.region',
            'eu-west1-a',
        );

        config()->set(
            'cloud.providers.arvan.defaults.size_id',
            'eco-2-2-0',
        );

        config()->set(
            'cloud.providers.arvan.defaults.image_id',
            '00aaa9d1-3e0a-468c-aaf4-334513981e42',
        );

        config()->set(
            'cloud.providers.arvan.defaults.network_id',
            'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
        );

        config()->set(
            'cloud.providers.arvan.defaults.security_group_id',
            '8449a4f5-5709-4017-9e63-45496bfe5cc9',
        );

        config()->set(
            'cloud.providers.arvan.defaults.security_group_name',
            'default',
        );
    }

    public function test_it_checks_cloud_catalog_defaults_and_inventory(): void
    {
        $this->fakeSuccessfulCatalog();

        $exitCode = Artisan::call('cloud:check');
        $output = Artisan::output();

        $this->assertSame(
            Command::SUCCESS,
            $exitCode,
        );

        $this->assertStringContainsString(
            'Cloud provider check passed.',
            $output,
        );

        $this->assertStringContainsString(
            'Provider: arvan',
            $output,
        );

        $this->assertStringContainsString(
            'Region: eu-west1-a',
            $output,
        );

        $this->assertStringContainsString(
            'Default: eco-2-2-0',
            $output,
        );

        $this->assertStringContainsString(
            'Default: Ubuntu 24.04',
            $output,
        );

        $this->assertStringContainsString(
            'Default: default',
            $output,
        );

        $this->assertStringContainsString(
            'Server inventory: 0',
            $output,
        );

        $this->assertStringContainsString(
            'SSH keys: skipped',
            $output,
        );

        Http::assertSentCount(7);
    }

    public function test_it_uses_the_region_selected_in_configuration(): void
    {
        $this->fakeSuccessfulCatalog();

        Artisan::call('cloud:check');

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                self::BASE_URL
                .'/regions/eu-west1-a/sizes',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                self::BASE_URL
                .'/regions/eu-west1-a/images'
                .'?type=distributions',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                self::BASE_URL
                .'/regions/eu-west1-a/securities',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                self::BASE_URL
                .'/regions/eu-west1-a/servers',
        );
    }

    public function test_provider_option_runs_read_only_liara_smoke_without_changing_default_provider(): void
    {
        config()->set(
            'cloud.providers.liara.base_url',
            self::LIARA_BASE_URL,
        );
        config()->set(
            'cloud.providers.liara.api_token',
            self::LIARA_TOKEN,
        );
        config()->set(
            'cloud.providers.liara.region',
            'iran',
        );
        config()->set(
            'cloud.providers.liara.defaults.size_id',
            'standard-base-g2',
        );
        config()->set(
            'cloud.providers.liara.defaults.image_id',
            'ubuntu-24.04',
        );

        Http::fake([
            self::LIARA_BASE_URL.'/plans' => Http::response([
                'plans' => [
                    'standard-base-g2' => [
                        'available' => true,
                        'region' => 'iran',
                        'monthlyPrice' => 1_050_000,
                        'hourlyPrice' => 1458.33,
                        'volume' => 20,
                        'RAM' => [
                            'amount' => 2,
                        ],
                        'CPU' => [
                            'amount' => 1,
                        ],
                        'IPv4MonthlyPrice' => [
                            200_000,
                            350_000,
                        ],
                        'IPv4HourlyPrice' => [
                            277.77,
                            486.11,
                        ],
                    ],
                ],
            ]),
            self::LIARA_BASE_URL.'/oss' => Http::response([
                'ubuntu' => [
                    '24.04',
                    '22.04',
                ],
            ]),
            self::LIARA_BASE_URL.'/vm' => Http::response([
                'vms' => [],
            ]),
        ]);

        $exitCode = Artisan::call(
            'cloud:check',
            [
                '--provider' => 'liara',
            ],
        );
        $output = Artisan::output();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame('arvan', config('cloud.default'));

        $this->assertStringContainsString(
            'Provider: liara',
            $output,
        );
        $this->assertStringContainsString(
            'Region: iran',
            $output,
        );
        $this->assertStringContainsString(
            'Default: standard-base-g2',
            $output,
        );
        $this->assertStringContainsString(
            'Default: ubuntu-24.04',
            $output,
        );
        $this->assertStringContainsString(
            'Networks and security groups: skipped',
            $output,
        );
        $this->assertStringContainsString(
            'Quota: skipped',
            $output,
        );
        $this->assertStringContainsString(
            'Server inventory: 0',
            $output,
        );
        $this->assertStringNotContainsString(
            self::LIARA_TOKEN,
            $output,
        );

        Http::assertSentCount(4);
        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::LIARA_BASE_URL.'/plans',
        );
        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::LIARA_BASE_URL.'/oss',
        );
        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::LIARA_BASE_URL.'/vm',
        );
        Http::assertNotSent(
            static fn (Request $request): bool => $request->method() !== 'GET',
        );
    }

    public function test_it_fails_when_a_configured_default_is_missing(): void
    {
        config()->set(
            'cloud.providers.arvan.defaults.size_id',
            'missing-size',
        );

        $this->fakeSuccessfulCatalog();

        $exitCode = Artisan::call('cloud:check');
        $output = Artisan::output();

        $this->assertSame(
            Command::FAILURE,
            $exitCode,
        );

        $this->assertStringContainsString(
            'Cloud provider configuration is invalid.',
            $output,
        );

        $this->assertStringNotContainsString(
            'missing-size',
            $output,
        );
    }

    public function test_it_returns_a_sanitized_authentication_failure(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Invalid API key: '.self::API_KEY,
                ],
                401,
            ),
        ]);

        $exitCode = Artisan::call('cloud:check');
        $output = Artisan::output();

        $this->assertSame(
            Command::FAILURE,
            $exitCode,
        );

        $this->assertStringContainsString(
            'Cloud provider authentication failed.',
            $output,
        );

        $this->assertStringNotContainsString(
            self::API_KEY,
            $output,
        );

        $this->assertStringNotContainsString(
            'Invalid API key',
            $output,
        );
    }

    public function test_it_never_prints_raw_json_or_api_key(): void
    {
        $this->fakeSuccessfulCatalog();

        Artisan::call('cloud:check');

        $output = Artisan::output();

        $this->assertStringNotContainsString(
            self::API_KEY,
            $output,
        );

        $this->assertStringNotContainsString(
            '"data"',
            $output,
        );

        $this->assertStringNotContainsString(
            'Authorization',
            $output,
        );

        $this->assertStringNotContainsString(
            'Apikey',
            $output,
        );
    }

    private function fakeSuccessfulCatalog(): void
    {
        Http::fake([
            self::BASE_URL.'/regions' => Http::response(
                $this->fixture('regions.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/sizes' => Http::response(
                $this->fixture('sizes.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/images*' => Http::response(
                $this->fixture('images.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/networks' => Http::response(
                $this->fixture('networks.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/securities' => Http::response(
                $this->fixture('security-groups.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/quota' => Http::response(
                $this->fixture('quota.json'),
            ),

            self::BASE_URL
            .'/regions/eu-west1-a/servers' => Http::response([
                'data' => [],
            ]),
        ]);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function fixture(string $name): array
    {
        $path = base_path(
            "tests/Fixtures/Cloud/ArvanCloud/{$name}",
        );

        $contents = file_get_contents($path);

        $this->assertNotFalse(
            $contents,
            "Unable to read fixture [{$name}].",
        );

        $payload = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($payload);

        return $payload;
    }
}
