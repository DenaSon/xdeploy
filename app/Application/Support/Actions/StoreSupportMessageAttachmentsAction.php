<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Application\Support\Contracts\SupportImageProcessorInterface;
use App\Application\Support\Data\ProcessedSupportImage;
use App\Application\Support\SupportAttachmentPolicy;
use App\Application\Support\SupportAttachmentValidationRules;
use App\Models\SupportMessage;
use App\Models\SupportMessageAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final readonly class StoreSupportMessageAttachmentsAction
{
    public function __construct(
        private SupportImageProcessorInterface $imageProcessor,
    ) {}

    /**
     * @param array<int, UploadedFile> $files
     * @return Collection<int, SupportMessageAttachment>
     */
    public function execute(
        SupportMessage $message,
        array $files,
    ): Collection {
        $files = array_values($files);

        if ($files === []) {
            return new Collection;
        }

        $this->validateFiles($files);

        $processedImages = array_map(
            fn (UploadedFile $file): ProcessedSupportImage => $this->imageProcessor->process($file),
            $files,
        );

        $storedPaths = [];

        try {
            return DB::transaction(
                function () use (
                    $message,
                    $processedImages,
                    &$storedPaths,
                ): Collection {
                    /** @var SupportMessage $lockedMessage */
                    $lockedMessage = SupportMessage::query()
                        ->whereKey($message->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $existingCount = $lockedMessage
                        ->attachments()
                        ->count();

                    if (
                        $existingCount + count($processedImages)
                        > SupportAttachmentPolicy::MAX_FILES
                    ) {
                        throw ValidationException::withMessages([
                            'attachments' => [
                                'برای هر پیام حداکثر دو تصویر مجاز است.',
                            ],
                        ]);
                    }

                    $nextSortOrder = ((int) $lockedMessage
                        ->attachments()
                        ->max('sort_order')) + 1;

                    /** @var Collection<int, SupportMessageAttachment> $attachments */
                    $attachments = new Collection;
                    $disk = Storage::disk(
                        SupportAttachmentPolicy::DISK,
                    );

                    foreach ($processedImages as $processedImage) {
                        $path = sprintf(
                            'support/messages/%d/%s.webp',
                            $lockedMessage->getKey(),
                            Str::uuid()->toString(),
                        );

                        $stored = $disk->put(
                            $path,
                            $processedImage->bytes,
                            'private',
                        );

                        if (! $stored) {
                            throw new RuntimeException(
                                'Support attachment could not be stored.',
                            );
                        }

                        $storedPaths[] = $path;

                        $attachments->push(
                            $lockedMessage
                                ->attachments()
                                ->create([
                                    'disk' => SupportAttachmentPolicy::DISK,
                                    'path' => $path,
                                    'mime_type' => $processedImage->mimeType,
                                    'size_bytes' => strlen(
                                        $processedImage->bytes,
                                    ),
                                    'width' => $processedImage->width,
                                    'height' => $processedImage->height,
                                    'sort_order' => $nextSortOrder,
                                ]),
                        );

                        $nextSortOrder++;
                    }

                    return $attachments;
                },
            );
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk(
                    SupportAttachmentPolicy::DISK,
                )->delete($storedPaths);
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    private function validateFiles(array $files): void
    {
        Validator::make(
            ['attachments' => $files],
            SupportAttachmentValidationRules::make(),
        )->validate();
    }
}
