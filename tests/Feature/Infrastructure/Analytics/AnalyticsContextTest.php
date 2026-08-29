<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Analytics;

use App\Infrastructure\Analytics\AnalyticsContext;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AnalyticsContextTest extends TestCase
{
    public function test_it_marks_admin_traffic_internal_and_sets_person_property(): void
    {
        config([
            'services.posthog.internal_user_ids' => [],
        ]);

        $user = new User;
        $user->forceFill([
            'id' => 42,
            'is_admin' => true,
        ]);

        $this->actingAs($user);

        Route::get(
            '/_analytics-context/admin',
            static fn (AnalyticsContext $context) => response()->json(
                $context->eventProperties(42),
            ),
        )->name('panel.analytics.test');

        $this->get('/_analytics-context/admin')
            ->assertOk()
            ->assertJson([
                'route_name' => 'panel.analytics.test',
                'is_internal' => true,
                '$set' => [
                    'is_internal' => true,
                ],
            ]);
    }

    public function test_it_marks_regular_authenticated_traffic_external(): void
    {
        config([
            'services.posthog.internal_user_ids' => [],
        ]);

        $user = new User;
        $user->forceFill([
            'id' => 43,
            'is_admin' => false,
        ]);

        $this->actingAs($user);

        Route::get(
            '/_analytics-context/regular',
            static fn (AnalyticsContext $context) => response()->json(
                $context->eventProperties(43),
            ),
        )->name('panel.analytics.regular');

        $this->get('/_analytics-context/regular')
            ->assertOk()
            ->assertJson([
                'route_name' => 'panel.analytics.regular',
                'is_internal' => false,
                '$set' => [
                    'is_internal' => false,
                ],
            ]);
    }

    public function test_configured_qa_accounts_are_internal_without_admin_role(): void
    {
        config([
            'services.posthog.internal_user_ids' => ['77'],
        ]);

        $user = new User;
        $user->forceFill([
            'id' => 77,
            'is_admin' => false,
        ]);

        $this->actingAs($user);

        Route::get(
            '/_analytics-context/qa',
            static fn (AnalyticsContext $context) => response()->json(
                $context->eventProperties(77),
            ),
        )->name('panel.analytics.qa');

        $this->get('/_analytics-context/qa')
            ->assertOk()
            ->assertJson([
                'route_name' => 'panel.analytics.qa',
                'is_internal' => true,
                '$set' => [
                    'is_internal' => true,
                ],
            ]);
    }

    public function test_it_does_not_overwrite_person_classification_for_an_unrelated_user(): void
    {
        $user = new User;
        $user->forceFill([
            'id' => 88,
            'is_admin' => true,
        ]);

        $this->actingAs($user);

        Route::get(
            '/_analytics-context/unrelated',
            static fn (AnalyticsContext $context) => response()->json(
                $context->eventProperties(99),
            ),
        )->name('panel.analytics.unrelated');

        $this->get('/_analytics-context/unrelated')
            ->assertOk()
            ->assertJson([
                'route_name' => 'panel.analytics.unrelated',
            ])
            ->assertJsonMissing([
                'is_internal' => true,
            ])
            ->assertJsonMissing([
                'is_internal' => false,
            ]);
    }
}
