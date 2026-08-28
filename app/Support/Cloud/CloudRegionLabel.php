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

        'frankfurt' => 'آلمان · فرانکفورت',
        'amsterdam' => 'هلند · آمستردام',
        'tehran2' => 'ایران · تهران',
        'tehran3' => 'ایران · تهران',
        'tehran11' => 'ایران · تهران',
        'tehran16' => 'ایران · تهران',
        'london1' => 'بریتانیا · لندن',
        'istanbul' => 'ترکیه · استانبول',
        'paris' => 'فرانسه · پاریس',
        'toronto2' => 'کانادا · تورنتو',
        'stockholm' => 'سوئد · استکهلم',
    ];

    public static function for(
        string $regionId,
    ): string {
        return self::LABELS[$regionId]
            ?? $regionId;
    }
}
