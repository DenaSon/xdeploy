<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Models\User;
use App\Support\Admin\AdminImpersonationSession;
use Throwable;

final readonly class AnalyticsContext
{
    public function __construct(
        private AdminImpersonationSession $impersonationSession,
    ) {}

    /**
     * Snapshot request-scoped analytics context before an event is queued.
     *
     * `is_internal` classifies this specific event/request. `$set.is_internal`
     * classifies the account itself, so admin impersonation never marks the
     * target customer's Person as an internal/test account.
     *
     * @return array<string, mixed>
     */
    public function eventProperties(int|string $userId): array
    {
        $properties = [];
        $routeName = $this->routeName();

        if ($routeName !== null) {
            $properties['route_name'] = $routeName;
        }

        $user = $this->currentUserFor($userId);

        if (! $user instanceof User) {
            return $properties;
        }

        $properties['is_internal'] = $this->isInternalTraffic($user);
        $properties['$set'] = [
            'is_internal' => $this->isInternalAccount($user),
        ];

        return $properties;
    }

    public function routeName(): ?string
    {
        try {
            $routeName = request()->route()?->getName();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($routeName)) {
            return null;
        }

        $routeName = trim($routeName);

        return $routeName !== '' ? $routeName : null;
    }

    public function currentTrafficIsInternal(): ?bool
    {
        $user = $this->currentUser();

        return $user instanceof User
            ? $this->isInternalTraffic($user)
            : null;
    }

    public function currentAccountIsInternal(): ?bool
    {
        $user = $this->currentUser();

        return $user instanceof User
            ? $this->isInternalAccount($user)
            : null;
    }

    public function isInternalTraffic(User $user): bool
    {
        if ($this->isInternalAccount($user)) {
            return true;
        }

        try {
            return request()->hasSession()
                && $this->impersonationSession->isActiveFor($user);
        } catch (Throwable) {
            return false;
        }
    }

    public function isInternalAccount(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $configuredUserIds = config(
            'services.posthog.internal_user_ids',
            [],
        );

        if (! is_array($configuredUserIds)) {
            return false;
        }

        $userId = trim((string) $user->getKey());

        foreach ($configuredUserIds as $configuredUserId) {
            if (
                $userId !== ''
                && hash_equals(
                    $userId,
                    trim((string) $configuredUserId),
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function currentUser(): ?User
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    private function currentUserFor(int|string $userId): ?User
    {
        $user = $this->currentUser();

        if (! $user instanceof User) {
            return null;
        }

        $expectedUserId = trim((string) $userId);
        $currentUserId = trim((string) $user->getKey());

        if (
            $expectedUserId === ''
            || $currentUserId === ''
            || ! hash_equals($expectedUserId, $currentUserId)
        ) {
            return null;
        }

        return $user;
    }
}
