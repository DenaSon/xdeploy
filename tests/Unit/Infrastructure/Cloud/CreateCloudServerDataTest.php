<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud;

use App\Domain\Cloud\DTOs\CreateCloudServerData;
use PHPUnit\Framework\TestCase;

final class CreateCloudServerDataTest extends TestCase
{
    public function test_provider_specific_infrastructure_is_optional(): void
    {
        $data = new CreateCloudServerData(
            name: 'coreflare-test',
            regionId: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            diskGiB: 20,
        );

        $this->assertNull($data->networkId);
        $this->assertSame([], $data->securityGroupIds);
        $this->assertFalse($data->hasAnyProvisioningInfrastructure());
        $this->assertFalse($data->hasProvisioningInfrastructure());
    }
}
