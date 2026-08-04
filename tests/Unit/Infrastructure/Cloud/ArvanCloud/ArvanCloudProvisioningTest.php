<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JsonException;
use Tests\TestCase;

final class ArvanCloudProvisioningTest extends TestCase
{
    private const BASE_URL =
        'https://api.example.test/ecc/v1';

    private const REGION_ID =
        'eu-west1-a';

    private const SERVER_ID =
        'ff83466c-c0fe-4dc4-9d1d-bde29efd0b45';

    private const SERVER_NAME =
        'xdeploy-test-server';

    private const GENERATED_PASSWORD =
        'temporary-generated-password';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_creates_a_password_server_from_minimal_create_response(): void
    {
        /*
         * The real ArvanCloud create response does not guarantee
         * name, status, username, or created-at fields.
         *
         * The create response is only responsible for returning
         * the provider server ID and generated password.
         */
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                [
                    'id' => self::SERVER_ID,

                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            self::SERVER_ID,
            $created->id,
        );

        /*
         * The response does not contain a name, so the provider
         * must preserve the name requested by xDeploy.
         */
        $this->assertSame(
            self::SERVER_NAME,
            $created->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $created->regionId,
        );

        /*
         * A successful POST only means that provisioning started.
         * The authoritative status is retrieved later through polling.
         */
        $this->assertSame(
            CloudServerStatus::Provisioning,
            $created->status,
        );

        /*
         * The default image username is supplied by provider
         * configuration when the response omits it.
         */
        $this->assertSame(
            'ubuntu',
            $created->username,
        );

        /*
         * The creation time is not guaranteed in the create response.
         * It will be obtained later from the server-list response.
         */
        $this->assertNull(
            $created->createdAt,
        );

        $this->assertSame(
            self::GENERATED_PASSWORD,
            $created->generatedPassword(),
        );

        $this->assertTrue(
            $created->hasGeneratedPassword(),
        );

        /*
         * Sensitive credentials must never appear in public arrays.
         */
        $this->assertArrayNotHasKey(
            'generated_password',
            $created->toArray(),
        );

        $this->assertArrayNotHasKey(
            'password',
            $created->toArray(),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    self::BASE_URL
                    .'/regions/'
                    .self::REGION_ID
                    .'/servers'
                    && $request->data() === [
                        'name' => self::SERVER_NAME,

                        'network_id' => 'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',

                        'flavor_id' => 'eco-1-1-0',

                        'image_id' => '00aaa9d1-3e0a-468c-aaf4-334513981e42',

                        'security_groups' => [
                            [
                                'name' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                            ],
                        ],

                        'ssh_key' => false,

                        'key_name' => null,

                        'count' => 1,

                        'create_type' => 'cinder',

                        'disk_size' => 25,

                        'init_script' => '',

                        'ha_enabled' => false,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_finds_an_active_server(): void
    {
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                $this->fixture(
                    'servers-active-response.json',
                ),
            ),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertInstanceOf(
            CloudServerData::class,
            $server,
        );

        $this->assertSame(
            self::SERVER_ID,
            $server->id,
        );

        $this->assertSame(
            CloudServerStatus::Active,
            $server->status,
        );

        $this->assertSame(
            'ubuntu',
            $server->username,
        );
        $this->assertSame(
            [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],
            $server->securityGroupIds,
        );

        $this->assertSame(
            [
                '185.204.168.213',
            ],
            $server->publicIpv4s(),
        );

        $this->assertTrue(
            $server->hasPublicIpv4(),
        );

        $this->assertTrue(
            $server->isReadyForSshCheck(),
        );

        $this->assertArrayNotHasKey(
            'password',
            $server->toArray(),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'GET'
                    && $request->url() ===
                    self::BASE_URL
                    .'/regions/'
                    .self::REGION_ID
                    .'/servers';
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_preserves_all_public_ips_and_deduplicates_references(): void
    {
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                $this->fixture(
                    'servers-multiple-ips-response.json',
                ),
            ),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertSame(
            [
                '185.204.168.213',
                '185.204.171.249',
            ],
            $server->publicIpv4s(),
        );

        $this->assertSame(
            [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],
            $server->securityGroupIds,
        );

        Http::assertSentCount(1);
    }

    public function test_it_throws_when_server_is_not_found(): void
    {
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                [
                    'data' => [],
                ],
            ),
        ]);

        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server [missing-server-id] was not found.',
        );

        $this->provider()->findServer(
            self::REGION_ID,
            'missing-server-id',
        );
    }

    public function test_it_requires_a_generated_password_for_password_flow(): void
    {
        /*
         * The response contains a provider server ID but no password.
         * Password-based provisioning must reject this response.
         */
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                [
                    'id' => self::SERVER_ID,
                ],
                201,
            ),
        ]);

        try {
            $this->provider()->createServer(
                $this->createData(),
            );

            $this->fail(
                'Expected CloudUnexpectedResponseException was not thrown.',
            );
        } catch (CloudUnexpectedResponseException $exception) {
            $this->assertSame(
                'ArvanCloud create response did not contain a generated password.',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);
    }

    public function test_it_uses_response_name_when_create_response_contains_name(): void
    {
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                [
                    'id' => self::SERVER_ID,

                    'name' => 'provider-returned-name',

                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            'provider-returned-name',
            $created->name,
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $created->status,
        );

        $this->assertNull(
            $created->createdAt,
        );

        Http::assertSentCount(1);
    }

    public function test_it_uses_requested_name_when_create_response_contains_empty_name(): void
    {
        Http::fake([
            self::BASE_URL
            .'/regions/'
            .self::REGION_ID
            .'/servers' => Http::response(
                [
                    'id' => self::SERVER_ID,

                    'name' => '   ',

                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            self::SERVER_NAME,
            $created->name,
        );

        Http::assertSentCount(1);
    }

    private function provider(): ArvanCloudProvider
    {
        return new ArvanCloudProvider(
            client: new ArvanCloudClient(
                baseUrl: self::BASE_URL,

                apiKey: 'test-api-key',

                connectTimeout: 5,

                requestTimeout: 15,
            ),

            mapper: new ArvanCloudResponseMapper,

            createType: 'cinder',

            defaultUsername: 'ubuntu',
        );
    }

    private function createData(): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: self::SERVER_NAME,

            regionId: self::REGION_ID,

            sizeId: 'eco-1-1-0',

            imageId: '00aaa9d1-3e0a-468c-aaf4-334513981e42',

            networkId: 'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',

            securityGroupIds: [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],

            diskGiB: 25,

            sshKeyName: null,

            initializationScript: '',

            highAvailability: false,
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function fixture(
        string $name,
    ): array {
        $path = base_path(
            "tests/Fixtures/Cloud/ArvanCloud/{$name}",
        );

        $contents = file_get_contents(
            $path,
        );

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

        $this->assertIsArray(
            $payload,
        );

        return $payload;
    }
}
