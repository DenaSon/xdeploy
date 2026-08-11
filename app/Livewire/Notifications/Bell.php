<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Bell extends Component
{
    public function refreshNotifications(): void
    {
        /*
         * Polling only triggers a fresh render.
         * Notification data is local persistence; no SSH/API work belongs here.
         */
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

        return view(
            'livewire.notifications.bell',
            [
                'unreadCount' => $user
                    ->unreadNotifications()
                    ->count(),

                'notifications' => $user
                    ->notifications()
                    ->latest()
                    ->limit(5)
                    ->get(),
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
