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

        $isInternal = $this->isInternal($user);

        $properties['is_internal'] = $isInternal;
        $properties['$set'] = [
            'is_internal' => $isInternal,
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

    public function currentUserIsInternal(): ?bool
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User
            ? $this->isInternal($user)
            : null;
    }

    public function isInternal(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        try {
            if (
                request()->hasSession()
                && $this->impersonationSession->isActiveFor($user)
            ) {
                return true;
            }
        } catch (Throwable) {
            // Analytics classification must never affect the product workflow.
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

    private function currentUserFor(int|string $userId): ?User
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

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
