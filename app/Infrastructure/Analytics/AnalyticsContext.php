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
        private AcquisitionAttribution $attribution,
    ) {}

    /**
     * Snapshot analytics context before an event is queued.
     *
     * `is_internal` classifies this specific event/request. `$set.is_internal`
     * classifies the account itself, so admin impersonation never marks the
     * target customer's Person as an internal/test account.
     *
     * Request-scoped acquisition attribution is added only when the current
     * authenticated user matches the event owner. This prevents one browser
     * session from leaking campaign context onto another user's event.
     *
     * When capture happens outside an authenticated request (for example in a
     * queue worker), the event owner is resolved by ID so admin/QA operations
     * keep the same classification as request-scoped events. Attribution is
     * intentionally not reconstructed in workers; PostHog Person properties
     * retain the first/last touch captured while the user was request-bound.
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

        $currentUser = $this->currentUser();

        if ($currentUser instanceof User) {
            if (! $this->userIdsMatch($currentUser, $userId)) {
                return $properties;
            }

            $properties = [
                ...$properties,
                ...$this->attribution->eventProperties(),
            ];

            $properties['is_internal'] = $this->isInternalTraffic($currentUser);
            $properties['$set'] = [
                'is_internal' => $this->isInternalAccount($currentUser),
                ...$this->attribution->lastTouchProperties(),
            ];

            $firstTouchProperties = $this->attribution->firstTouchProperties();

            if ($firstTouchProperties !== []) {
                $properties['$set_once'] = $firstTouchProperties;
            }

            return $properties;
        }

        $eventUserId = $this->normalizedUserId($userId);

        if ($eventUserId === null) {
            return $properties;
        }

        if ($this->isConfiguredInternalUserId($eventUserId)) {
            $properties['is_internal'] = true;
            $properties['$set'] = [
                'is_internal' => true,
            ];

            return $properties;
        }

        $eventOwner = $this->storedUser($eventUserId);

        if (! $eventOwner instanceof User) {
            return $properties;
        }

        $isInternal = $this->isInternalAccount($eventOwner);

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

        $userId = $this->normalizedUserId($user->getKey());

        return $userId !== null
            && $this->isConfiguredInternalUserId($userId);
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

    private function userIdsMatch(User $user, int|string $expectedUserId): bool
    {
        $expected = $this->normalizedUserId($expectedUserId);
        $current = $this->normalizedUserId($user->getKey());

        return $expected !== null
            && $current !== null
            && hash_equals($expected, $current);
    }

    private function normalizedUserId(mixed $userId): ?string
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        $normalized = trim((string) $userId);

        return $normalized !== '' ? $normalized : null;
    }

    private function isConfiguredInternalUserId(string $userId): bool
    {
        $configuredUserIds = config(
            'services.posthog.internal_user_ids',
            [],
        );

        if (! is_array($configuredUserIds)) {
            return false;
        }

        foreach ($configuredUserIds as $configuredUserId) {
            $configured = $this->normalizedUserId($configuredUserId);

            if (
                $configured !== null
                && hash_equals($userId, $configured)
            ) {
                return true;
            }
        }

        return false;
    }

    private function storedUser(string $userId): ?User
    {
        if (! ctype_digit($userId) || (int) $userId < 1) {
            return null;
        }

        try {
            $user = User::query()->find((int) $userId);
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }
}
