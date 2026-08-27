<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudVolumeManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArvanCloudVolumeManagerTest extends TestCase
{
    public function test_it_lists_and_filters_volumes_attached_to_a_server(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/regions/ir-thr-ba1/volumes' => Http::response([
                'data' => [
                    [
                        'id' => 'volume-root-1',
                        'name' => 'root-disk',
                        'status' => 'in-use',
                        'created_at' => '2026-08-26T08:00:00Z',
                        'attachments' => [
                            [
                                'id' => 'attachment-1',
                                'server_id' => 'server-123',
                                'server_name' => 'coreflare-vps',
                                'device' => '/dev/sda',
                                'attached_at' => '2026-08-26T08:01:00Z',
                            ],
                        ],
                    ],
                    [
                        'id' => 'volume-orphan-1',
                        'name' => 'detached-disk',
                        'status' => 'available',
                        'attachments' => [],
                    ],
                ],
            ], 200),
        ]);

        $volumes = $this->manager()->listAttachedToServer(
            region: 'ir-thr-ba1',
            serverId: 'server-123',
        );

        $this->assertCount(1, $volumes);
        $this->assertSame('volume-root-1', $volumes[0]->id);
        $this->assertSame('in-use', $volumes[0]->status);
        $this->assertTrue($volumes[0]->isAttachedTo('server-123'));
        $this->assertFalse($volumes[0]->isDetached());
        $this->assertSame('/dev/sda', $volumes[0]->attachments[0]->device);
        $this->assertSame(
            '2026-08-26T08:00:00+00:00',
            $volumes[0]->createdAt?->format(DATE_ATOM),
        );
    }

    public function test_it_reads_and_deletes_a_volume_through_the_v1_api(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/regions/ir-thr-ba1/volumes/volume-root-1' => Http::sequence()
                ->push([
                    'data' => [
                        'id' => 'volume-root-1',
                        'name' => 'root-disk',
                        'status' => 'available',
                        'attachments' => [],
                    ],
                ], 200)
                ->push([
                    'message' => 'Volume deleted successfully.',
                ], 200),
        ]);

        $manager = $this->manager();
        $volume = $manager->findVolume(
            region: 'ir-thr-ba1',
            volumeId: 'volume-root-1',
        );

        $this->assertSame('volume-root-1', $volume->id);
        $this->assertTrue($volume->isDetached());

        $manager->deleteVolume(
            region: 'ir-thr-ba1',
            volumeId: 'volume-root-1',
        );

        Http::assertSentCount(2);
        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() === 'https://api.example.test/ecc/v1/regions/ir-thr-ba1/volumes/volume-root-1'
                && $request->hasHeader('Authorization', 'Apikey test-api-key'),
        );
    }

    public function test_it_fails_closed_when_attachment_identity_is_missing(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/regions/ir-thr-ba1/volumes' => Http::response([
                'data' => [
                    [
                        'id' => 'volume-root-1',
                        'status' => 'in-use',
                        'attachments' => [
                            [
                                'device' => '/dev/sda',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(CloudUnexpectedResponseException::class);

        $this->manager()->listVolumes('ir-thr-ba1');
    }

    private function manager(): ArvanCloudVolumeManager
    {
        return new ArvanCloudVolumeManager(
            new ArvanCloudClient(
                baseUrl: 'https://api.example.test/ecc/v1',
                apiKey: 'test-api-key',
                connectTimeout: 1,
                requestTimeout: 2,
                retryMaxAttempts: 1,
            ),
        );
    }
}
