<?php

declare(strict_types=1);

namespace App\Application\Support;

final class SupportAttachmentValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function make(): array
    {
        return [
            'attachments' => [
                'array',
                'max:'.SupportAttachmentPolicy::MAX_FILES,
            ],
            'attachments.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.SupportAttachmentPolicy::MAX_KILOBYTES,
                'dimensions:max_width='.
                    SupportAttachmentPolicy::MAX_SOURCE_DIMENSION.
                    ',max_height='.
                    SupportAttachmentPolicy::MAX_SOURCE_DIMENSION,
            ],
        ];
    }
}
