<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use App\Livewire\Admin\Support\Show as AdminSupportShow;
use App\Livewire\Support\Create as SupportCreate;
use App\Livewire\Support\Show as SupportShow;
use App\Models\Server;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\Support\SupportRequestAnsweredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class SupportUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_pages_require_authentication(): void
    {
        $this->get(route('panel.support.index'))
            ->assertRedirect(route('login'));

        $this->get(route('panel.support.create'))
            ->assertRedirect(route('login'));
    }

    public function test_user_support_index_only_lists_owned_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $owned = $this->createSupportRequest(
            $user,
            'درخواست متعلق به من',
        );
        $this->createSupportRequest(
            $otherUser,
            'درخواست کاربر دیگر',
        );

        $this->actingAs($user)
            ->get(route('panel.support.index'))
            ->assertOk()
            ->assertSee('پشتیبانی')
            ->assertSee($owned->subject)
            ->assertDontSee('درخواست کاربر دیگر');
    }

    public function test_user_can_create_support_request_from_livewire_form(): void
    {
        $user = User::factory()->create();
        $server = $this->createServer(
            $user,
            '192.0.2.61',
        );

        $component = Livewire::actingAs($user)
            ->test(SupportCreate::class)
            ->set('subject', 'مشکل در پرداخت')
            ->set('category', 'billing')
            ->set('serverId', (string) $server->getKey())
            ->set('message', 'پرداخت انجام شده اما نتیجه هنوز مشخص نیست.')
            ->call('submit');

        /** @var SupportRequest $supportRequest */
        $supportRequest = SupportRequest::query()->firstOrFail();

        self::assertSame(
            SupportRequestCategory::Billing,
            $supportRequest->category,
        );
        self::assertSame(
            $server->getKey(),
            $supportRequest->server_id,
        );

        $component->assertRedirect(
            route(
                'panel.support.show',
                ['supportRequestId' => $supportRequest->getKey()],
            ),
        );
    }

    public function test_user_cannot_open_another_users_support_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $supportRequest = $this->createSupportRequest(
            $owner,
            'درخواست خصوصی',
        );

        $this->actingAs($otherUser)
            ->get(route(
                'panel.support.show',
                ['supportRequestId' => $supportRequest->getKey()],
            ))
            ->assertNotFound();
    }

    public function test_user_can_reply_and_close_from_support_conversation(): void
    {
        $user = User::factory()->create();
        $supportRequest = $this->createSupportRequest(
            $user,
            'مشکل فنی',
        );

        Livewire::actingAs($user)
            ->test(
                SupportShow::class,
                ['supportRequestId' => (string) $supportRequest->getKey()],
            )
            ->set('reply', 'جزئیات تکمیلی درخواست من')
            ->call('sendReply')
            ->assertSet('reply', '')
            ->assertSet('statusMessage', 'پاسخ شما با موفقیت ثبت شد.')
            ->call('close')
            ->assertSet('statusMessage', 'این درخواست بسته شد.');

        $supportRequest->refresh();

        self::assertSame(
            SupportRequestStatus::Closed,
            $supportRequest->status,
        );
        self::assertDatabaseHas('support_messages', [
            'support_request_id' => $supportRequest->getKey(),
            'body' => 'جزئیات تکمیلی درخواست من',
        ]);
    }

    public function test_admin_can_browse_support_and_reply_from_conversation(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $owner = User::factory()->create([
            'phone' => '09123334444',
        ]);
        $owner->profile()->create([
            'first_name' => 'کاربر',
            'last_name' => 'پشتیبانی',
        ]);

        $supportRequest = $this->createSupportRequest(
            $owner,
            'نیاز به بررسی مدیریت',
        );

        $this->actingAs($admin)
            ->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee('نیاز به بررسی مدیریت')
            ->assertSee('09123334444');

        $this->actingAs($admin)
            ->get(route(
                'admin.support.show',
                ['supportRequestId' => $supportRequest->getKey()],
            ))
            ->assertOk()
            ->assertSee('کاربر پشتیبانی');

        Livewire::actingAs($admin)
            ->test(
                AdminSupportShow::class,
                ['supportRequestId' => (string) $supportRequest->getKey()],
            )
            ->set('reply', 'درخواست بررسی شد و پاسخ مدیریت ثبت شد.')
            ->call('sendReply')
            ->assertSet('reply', '')
            ->assertSet(
                'statusMessage',
                'پاسخ مدیریت ثبت و برای کاربر اعلان شد.',
            );

        $supportRequest->refresh();

        self::assertSame(
            SupportRequestStatus::Answered,
            $supportRequest->status,
        );

        Notification::assertSentTo(
            $owner,
            SupportRequestAnsweredNotification::class,
        );
    }

    public function test_non_admin_cannot_open_admin_support_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.support.index'))
            ->assertForbidden();
    }

    public function test_support_answer_notification_links_to_user_conversation(): void
    {
        $notification = new SupportRequestAnsweredNotification(
            supportRequestId: 123,
            subject: 'درخواست تست',
        );

        $payload = $notification->toArray(
            User::factory()->create(),
        );

        self::assertSame(
            '/panel/support/123',
            $payload['action_url'],
        );
    }

    private function createSupportRequest(
        User $user,
        string $subject,
    ): SupportRequest {
        return app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: $subject,
            category: SupportRequestCategory::Technical,
            message: 'متن اولیه درخواست برای تست رابط پشتیبانی',
        );
    }

    private function admin(): User
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
            'name' => 'Support UI Server',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'status' => ServerStatus::Active,
        ]);
    }
}
