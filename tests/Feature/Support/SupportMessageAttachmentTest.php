<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Application\Support\Actions\StoreSupportMessageAttachmentsAction;
use App\Application\Support\SupportAttachmentPolicy;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Models\SupportMessage;
use App\Models\SupportMessageAttachment;
use App\Models\User;
use App\Support\Admin\AdminPasskeyVerificationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SupportMessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_images_are_optimized_and_stored_privately_for_a_message(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $message = $this->createMessage();

        $attachments = app(
            StoreSupportMessageAttachmentsAction::class,
        )->execute(
            message: $message,
            files: [
                UploadedFile::fake()->image(
                    'desktop.png',
                    2400,
                    1200,
                ),
                UploadedFile::fake()->image(
                    'mobile.jpg',
                    1200,
                    2400,
                ),
            ],
        );

        self::assertCount(2, $attachments);
        self::assertDatabaseCount(
            'support_message_attachments',
            2,
        );

        foreach ($attachments as $attachment) {
            self::assertSame(
                SupportAttachmentPolicy::DISK,
                $attachment->disk,
            );
            self::assertSame(
                SupportAttachmentPolicy::OUTPUT_MIME_TYPE,
                $attachment->mime_type,
            );
            self::assertLessThanOrEqual(
                SupportAttachmentPolicy::MAX_OUTPUT_DIMENSION,
                $attachment->width,
            );
            self::assertLessThanOrEqual(
                SupportAttachmentPolicy::MAX_OUTPUT_DIMENSION,
                $attachment->height,
            );
            self::assertStringEndsWith(
                '.webp',
                $attachment->path,
            );

            Storage::disk(
                SupportAttachmentPolicy::DISK,
            )->assertExists($attachment->path);

            $imageInfo = getimagesize(
                Storage::disk(
                    SupportAttachmentPolicy::DISK,
                )->path($attachment->path),
            );

            self::assertIsArray($imageInfo);
            self::assertSame(
                IMAGETYPE_WEBP,
                $imageInfo[2],
            );
            self::assertSame(
                $attachment->size_bytes,
                Storage::disk(
                    SupportAttachmentPolicy::DISK,
                )->size($attachment->path),
            );
        }
    }

    public function test_more_than_two_images_are_rejected_before_storage(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $message = $this->createMessage();

        try {
            app(StoreSupportMessageAttachmentsAction::class)->execute(
                message: $message,
                files: [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
            );

            self::fail('Expected attachment validation to fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'attachments',
                $exception->errors(),
            );
        }

        self::assertDatabaseCount(
            'support_message_attachments',
            0,
        );
        self::assertSame(
            [],
            Storage::disk(
                SupportAttachmentPolicy::DISK,
            )->allFiles(),
        );
    }

    public function test_existing_attachments_count_toward_the_per_message_limit(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $message = $this->createMessage();
        $action = app(StoreSupportMessageAttachmentsAction::class);

        $action->execute(
            message: $message,
            files: [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        );

        $this->expectException(
            ValidationException::class,
        );

        try {
            $action->execute(
                message: $message,
                files: [
                    UploadedFile::fake()->image('three.jpg'),
                ],
            );
        } finally {
            self::assertDatabaseCount(
                'support_message_attachments',
                2,
            );
        }
    }

    public function test_image_larger_than_two_megabytes_is_rejected(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $this->expectException(
            ValidationException::class,
        );

        app(StoreSupportMessageAttachmentsAction::class)->execute(
            message: $this->createMessage(),
            files: [
                UploadedFile::fake()
                    ->image('oversized.jpg')
                    ->size(
                        SupportAttachmentPolicy::MAX_KILOBYTES + 1,
                    ),
            ],
        );
    }

    public function test_unsupported_image_format_is_rejected(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $this->expectException(
            ValidationException::class,
        );

        app(StoreSupportMessageAttachmentsAction::class)->execute(
            message: $this->createMessage(),
            files: [
                UploadedFile::fake()->image('animated.gif'),
            ],
        );
    }

    public function test_user_attachment_route_is_scoped_to_request_owner(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $owner = User::factory()->create();
        $attachment = $this->createAttachment($owner);

        $ownerResponse = $this
            ->actingAs($owner)
            ->get(route(
                'panel.support.attachments.show',
                $attachment,
            ));

        $ownerResponse
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                SupportAttachmentPolicy::OUTPUT_MIME_TYPE,
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff',
            )
            ->assertHeader(
                'Referrer-Policy',
                'no-referrer',
            );

        self::assertStringContainsString(
            'no-store',
            (string) $ownerResponse->headers->get(
                'Cache-Control',
            ),
        );

        $this
            ->actingAs(User::factory()->create())
            ->get(route(
                'panel.support.attachments.show',
                $attachment,
            ))
            ->assertNotFound();

        $this
            ->actingAs($this->adminWithPasskey())
            ->get(route(
                'panel.support.attachments.show',
                $attachment,
            ))
            ->assertNotFound();
    }

    public function test_admin_attachment_route_requires_fresh_passkey_verification(): void
    {
        Storage::fake(SupportAttachmentPolicy::DISK);

        $attachment = $this->createAttachment();
        $admin = $this->adminWithPasskey();

        $this
            ->actingAs($admin)
            ->get(route(
                'admin.support.attachments.show',
                $attachment,
            ))
            ->assertRedirect(
                route('admin.passkey.confirm'),
            );

        $this
            ->actingAs($admin)
            ->withSession([
                AdminPasskeyVerificationSession::SESSION_KEY => [
                    'admin_user_id' => (int) $admin->getKey(),
                    'verified_at' => now()->timestamp,
                ],
            ])
            ->get(route(
                'admin.support.attachments.show',
                $attachment,
            ))
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                SupportAttachmentPolicy::OUTPUT_MIME_TYPE,
            );
    }

    protected function automaticallyVerifyAdminPasskey(): bool
    {
        return false;
    }

    private function createAttachment(
        ?User $owner = null,
    ): SupportMessageAttachment {
        /** @var SupportMessageAttachment $attachment */
        $attachment = app(
            StoreSupportMessageAttachmentsAction::class,
        )->execute(
            message: $this->createMessage($owner),
            files: [
                UploadedFile::fake()->image('error.png'),
            ],
        )->firstOrFail();

        return $attachment;
    }

    private function createMessage(
        ?User $owner = null,
    ): SupportMessage {
        $owner ??= User::factory()->create();

        $supportRequest = app(
            CreateSupportRequestAction::class,
        )->execute(
            user: $owner,
            subject: 'خطای سرویس',
            category: SupportRequestCategory::Technical,
            message: 'برای بررسی خطا تصویر ارسال می‌شود.',
        );

        return $supportRequest
            ->messages()
            ->firstOrFail();
    }

    private function adminWithPasskey(): User
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $admin->passkeys()->create([
            'name' => 'Admin device',
            'credential_id' => 'c3VwcG9ydC1hdHRhY2htZW50LWFkbWlu',
            'credential' => [
                'aaguid' => '00000000-0000-0000-0000-000000000000',
            ],
        ]);

        return $admin;
    }
}
