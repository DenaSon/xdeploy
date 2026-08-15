<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Support\Actions\CloseSupportRequestAction;
use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Application\Support\Actions\ReplyToSupportRequestAsAdminAction;
use App\Application\Support\Actions\ReplyToSupportRequestAsUserAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Support\Enums\SupportMessageAuthorRole;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use App\Domain\Support\Exceptions\SupportRequestClosedException;
use App\Models\Server;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\Support\SupportRequestAnsweredNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

final class SupportBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_support_request_with_initial_message_and_owned_server(): void
    {
        $user = User::factory()->create();
        $server = $this->createServer($user, '192.0.2.41');

        $supportRequest = app(
            CreateSupportRequestAction::class,
        )->execute(
            user: $user,
            subject: '  مشکل در نصب n8n  ',
            category: SupportRequestCategory::Technical,
            message: '  نصب در مرحله آماده‌سازی متوقف شده است.  ',
            serverId: (int) $server->getKey(),
        );

        self::assertSame(
            'مشکل در نصب n8n',
            $supportRequest->subject,
        );
        self::assertSame(
            SupportRequestCategory::Technical,
            $supportRequest->category,
        );
        self::assertSame(
            SupportRequestStatus::Open,
            $supportRequest->status,
        );
        self::assertSame(
            $user->getKey(),
            $supportRequest->user_id,
        );
        self::assertSame(
            $server->getKey(),
            $supportRequest->server_id,
        );
        self::assertNotNull(
            $supportRequest->last_message_at,
        );
        self::assertNull(
            $supportRequest->closed_at,
        );

        $message = $supportRequest->messages()->firstOrFail();

        self::assertSame(
            'نصب در مرحله آماده‌سازی متوقف شده است.',
            $message->body,
        );
        self::assertSame(
            SupportMessageAuthorRole::User,
            $message->author_role,
        );
        self::assertSame(
            $user->getKey(),
            $message->author_id,
        );
    }

    public function test_support_request_cannot_attach_another_users_server(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherServer = $this->createServer(
            $otherUser,
            '192.0.2.42',
        );

        $this->expectException(
            ModelNotFoundException::class,
        );

        try {
            app(CreateSupportRequestAction::class)->execute(
                user: $user,
                subject: 'مشکل سرور',
                category: SupportRequestCategory::Technical,
                message: 'لطفاً وضعیت سرور را بررسی کنید.',
                serverId: (int) $otherServer->getKey(),
            );
        } finally {
            self::assertDatabaseCount(
                'support_requests',
                0,
            );
        }
    }

    public function test_support_content_rejects_blank_required_values(): void
    {
        $user = User::factory()->create();

        $this->expectException(
            InvalidArgumentException::class,
        );

        app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: '   ',
            category: SupportRequestCategory::Other,
            message: 'متن معتبر',
        );
    }

    public function test_admin_reply_marks_request_answered_and_notifies_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = $this->createAdmin();
        $supportRequest = $this->createSupportRequest($owner);

        $message = app(
            ReplyToSupportRequestAsAdminAction::class,
        )->execute(
            admin: $admin,
            supportRequestId: (int) $supportRequest->getKey(),
            message: '  مشکل بررسی شد و سرویس در دسترس است.  ',
        );

        $supportRequest->refresh();

        self::assertSame(
            SupportRequestStatus::Answered,
            $supportRequest->status,
        );
        self::assertSame(
            SupportMessageAuthorRole::Admin,
            $message->author_role,
        );
        self::assertSame(
            $admin->getKey(),
            $message->author_id,
        );
        self::assertSame(
            'مشکل بررسی شد و سرویس در دسترس است.',
            $message->body,
        );

        Notification::assertSentTo(
            $owner,
            SupportRequestAnsweredNotification::class,
            static fn (
                SupportRequestAnsweredNotification $notification,
            ): bool => $notification->supportRequestId
                === (int) $supportRequest->getKey(),
        );
    }

    public function test_non_admin_cannot_reply_as_support_staff(): void
    {
        $owner = User::factory()->create();
        $ordinaryUser = User::factory()->create();
        $supportRequest = $this->createSupportRequest($owner);

        $this->expectException(
            AuthorizationException::class,
        );

        app(ReplyToSupportRequestAsAdminAction::class)->execute(
            admin: $ordinaryUser,
            supportRequestId: (int) $supportRequest->getKey(),
            message: 'پاسخ غیرمجاز',
        );
    }

    public function test_owner_reply_moves_answered_request_back_to_open(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = $this->createAdmin();
        $supportRequest = $this->createSupportRequest($owner);

        app(ReplyToSupportRequestAsAdminAction::class)->execute(
            admin: $admin,
            supportRequestId: (int) $supportRequest->getKey(),
            message: 'لطفاً نتیجه را بررسی کنید.',
        );

        $message = app(
            ReplyToSupportRequestAsUserAction::class,
        )->execute(
            user: $owner,
            supportRequestId: (int) $supportRequest->getKey(),
            message: 'هنوز مشکل وجود دارد.',
        );

        $supportRequest->refresh();

        self::assertSame(
            SupportRequestStatus::Open,
            $supportRequest->status,
        );
        self::assertSame(
            SupportMessageAuthorRole::User,
            $message->author_role,
        );
        self::assertSame(
            $owner->getKey(),
            $message->author_id,
        );
        self::assertDatabaseCount(
            'support_messages',
            3,
        );
    }

    public function test_user_cannot_reply_to_another_users_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $supportRequest = $this->createSupportRequest($owner);

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(ReplyToSupportRequestAsUserAction::class)->execute(
            user: $otherUser,
            supportRequestId: (int) $supportRequest->getKey(),
            message: 'پیام غیرمجاز',
        );
    }

    public function test_owner_can_close_request_and_closed_request_rejects_new_messages(): void
    {
        $owner = User::factory()->create();
        $supportRequest = $this->createSupportRequest($owner);

        app(CloseSupportRequestAction::class)->execute(
            actor: $owner,
            supportRequestId: (int) $supportRequest->getKey(),
        );

        $supportRequest->refresh();

        self::assertSame(
            SupportRequestStatus::Closed,
            $supportRequest->status,
        );
        self::assertNotNull(
            $supportRequest->closed_at,
        );

        $this->expectException(
            SupportRequestClosedException::class,
        );

        app(ReplyToSupportRequestAsUserAction::class)->execute(
            user: $owner,
            supportRequestId: (int) $supportRequest->getKey(),
            message: 'پیام بعد از بسته‌شدن',
        );
    }

    public function test_admin_can_close_any_request_and_close_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $admin = $this->createAdmin();
        $supportRequest = $this->createSupportRequest($owner);

        $firstClose = app(CloseSupportRequestAction::class)->execute(
            actor: $admin,
            supportRequestId: (int) $supportRequest->getKey(),
        );

        $closedAt = $firstClose->closed_at;

        $secondClose = app(CloseSupportRequestAction::class)->execute(
            actor: $admin,
            supportRequestId: (int) $supportRequest->getKey(),
        );

        self::assertSame(
            SupportRequestStatus::Closed,
            $secondClose->status,
        );
        self::assertTrue(
            $secondClose->closed_at?->equalTo($closedAt) === true,
        );
    }

    public function test_regular_user_cannot_close_another_users_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $supportRequest = $this->createSupportRequest($owner);

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(CloseSupportRequestAction::class)->execute(
            actor: $otherUser,
            supportRequestId: (int) $supportRequest->getKey(),
        );
    }

    private function createSupportRequest(
        User $user,
    ): SupportRequest {
        return app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: 'درخواست تست',
            category: SupportRequestCategory::Technical,
            message: 'متن اولیه درخواست پشتیبانی',
        );
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        return $admin;
    }

    private function createServer(
        User $user,
        string $host,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Support Test Server',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'status' => ServerStatus::Active,
        ]);
    }
}
