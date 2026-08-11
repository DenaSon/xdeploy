<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.panel')]
#[Title('اعلان‌ها')]
final class Index extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function setFilter(
        string $filter,
    ): void {
        abort_unless(
            in_array(
                $filter,
                [
                    'all',
                    'unread',
                ],
                true,
            ),
            422,
        );

        $this->filter = $filter;

        $this->resetPage();
    }

    public function markAsRead(
        string $notificationId,
    ): void {
        $this->ownedNotification(
            $notificationId,
        )->markAsRead();
    }

    public function markAllAsRead(): void
    {
        $this->authenticatedUser()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);
    }

    public function openNotification(
        string $notificationId,
    ): mixed {
        $notification =
            $this->ownedNotification(
                $notificationId,
            );

        $notification->markAsRead();

        return redirect()->to(
            $this->safeActionUrl(
                $notification,
            ),
        );
    }

    public function render(): View
    {
        $user = $this->authenticatedUser();

        $query = $user
            ->notifications()
            ->latest();

        if ($this->filter === 'unread') {
            $query->whereNull(
                'read_at',
            );
        }

        return view(
            'livewire.notifications.index',
            [
                'notifications' => $query->paginate(20),

                'unreadCount' => $user
                    ->unreadNotifications()
                    ->count(),
            ],
        );
    }

    private function ownedNotification(
        string $notificationId,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification =
            $this->authenticatedUser()
                ->notifications()
                ->whereKey(
                    $notificationId,
                )
                ->firstOrFail();

        return $notification;
    }

    private function safeActionUrl(
        DatabaseNotification $notification,
    ): string {
        $url = $notification->data[
            'action_url'
        ] ?? null;

        if (
            is_string($url)
            && str_starts_with(
                $url,
                '/',
            )
            && ! str_starts_with(
                $url,
                '//',
            )
        ) {
            return $url;
        }

        return route(
            'panel.notifications.index',
            absolute: false,
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
