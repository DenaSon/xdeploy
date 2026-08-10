<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Actions;

use App\Application\Cloud\Actions\FilterSupportedCloudImagesAction;
use App\Application\Cloud\Actions\ListSupportedCloudImagesAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ListSupportedCloudImagesActionTest extends TestCase
{
    public function test_it_returns_only_images_supported_by_xdeploy_policy(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->image(
                    id: 'ubuntu-24',
                    distribution: 'Ubuntu',
                    version: '24.04',
                ),

                $this->image(
                    id: 'ubuntu-22',
                    distribution: 'Ubuntu',
                    version: '22.04',
                ),

                $this->image(
                    id: 'ubuntu-26',
                    distribution: 'Ubuntu',
                    version: '26.04',
                ),

                $this->image(
                    id: 'debian-12',
                    distribution: 'Debian',
                    version: '12',
                ),
            ]);

        $images = $this->action(
            cloud: $cloud,
        )->execute(
            'eu-west1-a',
        );

        $this->assertCount(
            1,
            $images,
        );

        $this->assertSame(
            'ubuntu-24',
            $images[0]->id,
        );

        $this->assertSame(
            'Ubuntu',
            $images[0]->distribution,
        );

        $this->assertSame(
            '24.04',
            $images[0]->version,
        );
    }

    public function test_it_excludes_supported_os_image_without_password_authentication(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->image(
                    id: 'ubuntu-24-password',
                    distribution: 'Ubuntu',
                    version: '24.04',
                    supportsPassword: true,
                ),

                $this->image(
                    id: 'ubuntu-24-key-only',
                    distribution: 'Ubuntu',
                    version: '24.04',
                    supportsPassword: false,
                ),
            ]);

        $images = $this->action(
            cloud: $cloud,
        )->execute(
            'eu-west1-a',
        );

        $this->assertCount(
            1,
            $images,
        );

        $this->assertSame(
            'ubuntu-24-password',
            $images[0]->id,
        );
    }

    public function test_it_uses_policy_matrix_instead_of_provider_availability(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->image(
                    id: 'ubuntu-24',
                    distribution: 'Ubuntu',
                    version: '24.04',
                ),

                $this->image(
                    id: 'debian-12',
                    distribution: 'Debian',
                    version: '12',
                ),
            ]);

        $filter = new FilterSupportedCloudImagesAction(
            operatingSystems: new SupportedOperatingSystemPolicy(
                matrix: [
                    'ubuntu' => [
                        '24.04',
                    ],

                    'debian' => [
                        '12',
                    ],
                ],
            ),
        );

        $images = new ListSupportedCloudImagesAction(
            cloud: $cloud,
            filter: $filter,
        );

        $result = $images->execute(
            'eu-west1-a',
        );

        $this->assertSame(
            [
                'ubuntu-24',
                'debian-12',
            ],
            array_map(
                static fn (CloudImageData $image): string => $image->id,
                $result,
            ),
        );
    }

    public function test_it_rejects_empty_region_before_calling_provider(): void
    {
        $cloud = $this->cloud();

        $cloud->shouldNotReceive(
            'listImages',
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        $this->action(
            cloud: $cloud,
        )->execute(
            '   ',
        );
    }

    /**
     * @return CloudProviderInterface&MockInterface
     */
    private function cloud(): CloudProviderInterface
    {
        return Mockery::mock(
            CloudProviderInterface::class,
        );
    }

    private function action(
        CloudProviderInterface $cloud,
    ): ListSupportedCloudImagesAction {
        return new ListSupportedCloudImagesAction(
            cloud: $cloud,

            filter: new FilterSupportedCloudImagesAction(
                operatingSystems: new SupportedOperatingSystemPolicy(
                    matrix: [
                        'ubuntu' => [
                            '24.04',
                        ],
                    ],
                ),
            ),
        );
    }

    private function image(
        string $id,
        string $distribution,
        string $version,
        bool $supportsPassword = true,
    ): CloudImageData {
        return new CloudImageData(
            id: $id,

            name: "{$distribution} {$version}",

            regionId: 'eu-west1-a',

            distribution: $distribution,

            version: $version,

            architecture: null,

            minDiskGiB: null,

            minMemoryMiB: null,

            supportsSshKey: true,

            supportsPassword: $supportsPassword,
        );
    }
}
