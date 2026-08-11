<?php

declare(strict_types=1);

namespace App\Support\Cloud;

final class CloudRegionLabel
{
    private const array LABELS = [
        'ir-thr-ba1' => 'تهران · آریا',
        'ir-thr-fr1' => 'تهران · سورن',
        'ir-thr-si1' => 'تهران · سپهر',
        'ir-tbz-sh1' => 'غرب ایران · آذر',
        'ir-southwest1-a' => 'جنوب‌غرب · کارون',
        'eu-west1-a' => 'اروپا · گوته',
    ];

    public static function for(
        string $regionId,
    ): string {
        return self::LABELS[$regionId]
            ?? $regionId;
    }
}
