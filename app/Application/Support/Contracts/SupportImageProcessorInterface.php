<?php

declare(strict_types=1);

namespace App\Application\Support\Contracts;

use App\Application\Support\Data\ProcessedSupportImage;
use Illuminate\Http\UploadedFile;

interface SupportImageProcessorInterface
{
    public function process(
        UploadedFile $file,
    ): ProcessedSupportImage;
}
