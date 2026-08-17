<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Livewire\Admin\Support\Show as AdminSupportShow;
use App\Livewire\Support\Create as SupportCreate;
use App\Livewire\Support\Show as SupportShow;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class SupportAttachmentUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_support_request_with_images(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(SupportCreate::class)
            ->set('subject', 'خطای نصب برنامه')
            ->set('category', 'technical')
            ->set('message', 'برای بررسی خطا دو تصویر ارسال کرده‌ام.')
            ->set('attachments', [
                UploadedFile::fake()->image('error-1.png', 1200, 800),
                UploadedFile::fake()->image('error-2.jpg', 900, 700),
            ])
            ->call('submit');

        /** @var SupportRequest $supportRequest */
        $supportRequest = SupportRequest::query()
            ->with('messages.attachments')
            ->firstOrFail();

        $attachments = $supportRequest
            ->messages
            ->firstOrFail()
            ->attachments;

        self::assertCount(2, $attachments);

        foreach ($attachments as $attachment) {
            self::assertSame('image/webp', $attachment->mime_type);
            self::assertLessThanOrEqual(1600, $attachment->width);
            self::assertLessThanOrEqual(1600, $attachment->height);
            Storage::disk('local')->assertExists($attachment->path);
        }

        $component->assertRedirect(route(
            'panel.support.show',
            ['supportRequestId' => $supportRequest->getKey()],
        ));
    }

    public function test_user_can_reply_with_image_and_upload_state_is_reset(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $supportRequest = $this->createSupportRequest($user);

        Livewire::actingAs($user)
            ->test(
                SupportShow::class,
                ['supportRequestId' => (string) $supportRequest->getKey()],
            )
            ->set('reply', 'این تصویر وضعیت فعلی خطا را نشان می‌دهد.')
            ->set('attachments', [
                UploadedFile::fake()->image('reply.png', 1000, 700),
            ])
            ->call('sendReply')
            ->assertSet('reply', '')
            ->assertSet('attachments', [])
            ->assertSet('statusMessage', 'پاسخ شما با موفقیت ثبت شد.');

        $supportRequest->refresh();

        $reply = $supportRequest
            ->messages()
            ->reorder()
            ->with('attachments')
            ->latest('id')
            ->firstOrFail();

        self::assertCount(1, $reply->attachments);
        Storage::disk('local')->assertExists(
            $reply->attachments->firstOrFail()->path,
        );
    }

    public function test_livewire_rejects_more_than_two_images_before_creating_request(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreate::class)
            ->set('subject', 'درخواست دارای تصاویر زیاد')
            ->set('category', 'technical')
            ->set('message', 'این درخواست نباید ثبت شود.')
            ->set('attachments', [
                UploadedFile::fake()->image('one.png', 400, 300),
                UploadedFile::fake()->image('two.png', 400, 300),
                UploadedFile::fake()->image('three.png', 400, 300),
            ])
            ->call('submit')
            ->assertHasErrors(['attachments']);

        self::assertDatabaseCount('support_requests', 0);
        self::assertDatabaseCount('support_message_attachments', 0);
    }

    public function test_user_and_admin_conversations_render_private_attachment_urls(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $supportRequest = app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: 'درخواست دارای تصویر',
            category: SupportRequestCategory::Technical,
            message: 'تصویر خطا برای بررسی پیوست شده است.',
            attachments: [
                UploadedFile::fake()->image('error.png', 800, 600),
            ],
        );

        $attachment = $supportRequest
            ->messages()
            ->with('attachments')
            ->firstOrFail()
            ->attachments
            ->firstOrFail();

        Livewire::actingAs($user)
            ->test(
                SupportShow::class,
                ['supportRequestId' => (string) $supportRequest->getKey()],
            )
            ->assertSeeHtml('data-support-message-attachments')
            ->assertSeeHtml(route(
                'panel.support.attachments.show',
                ['attachment' => $attachment],
            ));

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Livewire::actingAs($admin)
            ->test(
                AdminSupportShow::class,
                ['supportRequestId' => (string) $supportRequest->getKey()],
            )
            ->assertSeeHtml('data-support-message-attachments')
            ->assertSeeHtml(route(
                'admin.support.attachments.show',
                ['attachment' => $attachment],
            ));
    }

    private function createSupportRequest(User $user): SupportRequest
    {
        return app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: 'درخواست تست پیوست',
            category: SupportRequestCategory::Technical,
            message: 'متن اولیه درخواست پشتیبانی',
        );
    }
}
