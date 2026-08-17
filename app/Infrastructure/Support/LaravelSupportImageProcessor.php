<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use App\Application\Support\Contracts\SupportImageProcessorInterface;
use App\Application\Support\Data\ProcessedSupportImage;
use App\Application\Support\SupportAttachmentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;

final readonly class LaravelSupportImageProcessor implements SupportImageProcessorInterface
{
    public function process(
        UploadedFile $file,
    ): ProcessedSupportImage {
        $image = Image::fromUpload($file)
            ->orient()
            ->scale(
                width: SupportAttachmentPolicy::MAX_OUTPUT_DIMENSION,
                height: SupportAttachmentPolicy::MAX_OUTPUT_DIMENSION,
            )
            ->optimize(
                format: 'webp',
                quality: SupportAttachmentPolicy::WEBP_QUALITY,
            );

        [$width, $height] = $image->dimensions();

        return new ProcessedSupportImage(
            bytes: $image->toBytes(),
            mimeType: $image->mimeType(),
            width: $width,
            height: $height,
        );
    }
}
