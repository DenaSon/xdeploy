<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Actions;

use App\Application\Cloud\Actions\FilterSupportedCloudImagesAction;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use PHPUnit\Framework\TestCase;

final class FilterSupportedCloudImagesActionTest extends TestCase
{
    public function test_it_orders_supported_images_by_stable_purchase_preference(): void
    {
        $action = new FilterSupportedCloudImagesAction(
            new SupportedOperatingSystemPolicy([
                'ubuntu' => ['22.04', '24.04'],
                'debian' => ['12'],
            ]),
        );

        $images = [
            $this->image('debian-12', 'debian', '12'),
            $this->image('ubuntu-22', 'ubuntu', '22.04'),
            $this->image('ubuntu-24', 'ubuntu', '24.04'),
        ];

        self::assertSame(
            ['ubuntu-24', 'ubuntu-22', 'debian-12'],
            array_map(
                static fn (CloudImageData $image): string => $image->id,
                $action->execute($images),
            ),
        );
    }

    public function test_it_preserves_provider_order_within_the_same_fallback_group(): void
    {
        $action = new FilterSupportedCloudImagesAction(
            new SupportedOperatingSystemPolicy([
                'debian' => ['12', '13'],
            ]),
        );

        $images = [
            $this->image('debian-13', 'debian', '13'),
            $this->image('debian-12', 'debian', '12'),
        ];

        self::assertSame(
            ['debian-13', 'debian-12'],
            array_map(
                static fn (CloudImageData $image): string => $image->id,
                $action->execute($images),
            ),
        );
    }

    private function image(
        string $id,
        string $distribution,
        string $version,
    ): CloudImageData {
        return new CloudImageData(
            id: $id,
            name: $id,
            regionId: 'test-region',
            distribution: $distribution,
            version: $version,
            architecture: 'x86_64',
            minDiskGiB: 20,
            minMemoryMiB: 1024,
            supportsSshKey: true,
            supportsPassword: true,
        );
    }
}
