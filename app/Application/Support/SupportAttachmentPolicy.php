<?php

declare(strict_types=1);

namespace App\Application\Support;

final class SupportAttachmentPolicy
{
    public const int MAX_FILES = 2;

    public const int MAX_KILOBYTES = 2048;

    public const int MAX_SOURCE_DIMENSION = 4096;

    public const int MAX_OUTPUT_DIMENSION = 1600;

    public const int WEBP_QUALITY = 75;

    public const string DISK = 'local';

    public const string OUTPUT_MIME_TYPE = 'image/webp';
}
