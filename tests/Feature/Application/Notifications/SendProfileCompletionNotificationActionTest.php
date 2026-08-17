<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Notifications;

use App\Application\Notifications\Actions\SendProfileCompletionNotificationAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SendProfileCompletionNotificationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profile_receives_completion_notification_once(): void
    {
        $user = User::factory()->create();

        $action = app(
            SendProfileCompletionNotificationAction::class,
        );

        $action->execute($user);
        $action->execute($user);

        $this->assertSame(
            1,
            $user->notifications()->count(),
        );

        $notification = $user
            ->notifications()
            ->firstOrFail();

        $this->assertSame(
            'profile_completion_required',
            $notification->data['kind'],
        );

        $this->assertSame(
            route('panel.profile', absolute: false),
            $notification->data['action_url'],
        );
    }

    public function test_complete_profile_does_not_receive_completion_notification(): void
    {
        $user = User::factory()->create([
            'email' => 'complete@example.com',
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'first_name' => 'محمد',
            'last_name' => 'اسدی',
        ]);

        app(
            SendProfileCompletionNotificationAction::class,
        )->execute($user);

        $this->assertSame(
            0,
            $user->notifications()->count(),
        );
    }
}
