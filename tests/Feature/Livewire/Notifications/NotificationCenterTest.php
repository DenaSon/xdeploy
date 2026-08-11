<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\Bell;
use App\Livewire\Notifications\Index;
use App\Models\User;
use App\Notifications\Cloud\CloudServerExpiringSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_page_shows_owned_database_notifications_and_marks_all_as_read(): void
    {
        $user = User::factory()->create();

        Notification::sendNow(
            $user,
            new CloudServerExpiringSoonNotification(
                serverId: 42,
                serverName: 'Cloud VPS',
                expiresAt: now()
                    ->addHours(23)
                    ->toIso8601String(),
            ),
            [
                'database',
            ],
        );

        $this->actingAs(
            $user,
        );

        Livewire::test(
            Index::class,
        )
            ->assertSee(
                'پایان سرویس نزدیک است',
            )
            ->call(
                'markAllAsRead',
            );

        $this->assertSame(
            0,
            $user
                ->unreadNotifications()
                ->count(),
        );
    }

    public function test_header_bell_shows_unread_notification_count(): void
    {
        $user = User::factory()->create();

        Notification::sendNow(
            $user,
            new CloudServerExpiringSoonNotification(
                serverId: 42,
                serverName: 'Cloud VPS',
                expiresAt: now()
                    ->addHours(23)
                    ->toIso8601String(),
            ),
            [
                'database',
            ],
        );

        $this->actingAs(
            $user,
        );

        Livewire::test(
            Bell::class,
        )
            ->assertSee(
                'پایان سرویس نزدیک است',
            )
            ->assertSee(
                '1',
            );
    }
}
