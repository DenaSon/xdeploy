<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Cloud;

use App\Support\Cloud\CloudRegionLabel;
use PHPUnit\Framework\TestCase;

final class CloudRegionLabelTest extends TestCase
{
    public function test_known_region_has_a_display_label(): void
    {
        $this->assertNotSame(
            'ir-thr-ba1',
            CloudRegionLabel::for(
                'ir-thr-ba1',
            ),
        );
    }

    public function test_unknown_region_falls_back_to_region_id(): void
    {
        $this->assertSame(
            'unknown-region',
            CloudRegionLabel::for(
                'unknown-region',
            ),
        );
    }
}
