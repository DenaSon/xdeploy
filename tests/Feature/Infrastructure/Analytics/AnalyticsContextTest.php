<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Analytics;

use App\Infrastructure\Analytics\AnalyticsContext;
use App\Models\User;
use App\Support\Admin\AdminImpersonationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AnalyticsContextTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_impersonation_is_internal_traffic_without_reclassifying_the_customer(): void
    {
        config([
            'services.posthog.internal_user_ids' => [],
        ]);

        $target = new User;
        $target->forceFill([
            'id' => 71,
            'is_admin' => false,
        ]);

        $this->actingAs($target);

        Route::middleware('web')
            ->get(
                '/_analytics-context/impersonated',
                static fn (AnalyticsContext $context) => response()->json(
                    $context->eventProperties(71),
                ),
            )
            ->name('panel.analytics.impersonated');

        $this->withSession([
            AdminImpersonationSession::SESSION_KEY => [
                'admin_user_id' => 1,
                'target_user_id' => 71,
            ],
        ])->get('/_analytics-context/impersonated')
            ->assertOk()
            ->assertJson([
                'route_name' => 'panel.analytics.impersonated',
                'is_internal' => true,
                '$set' => [
                    'is_internal' => false,
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

    public function test_it_classifies_admin_event_owner_without_an_authenticated_request(): void
    {
        config([
            'services.posthog.internal_user_ids' => [],
        ]);

        $user = User::factory()->create();
        $user->forceFill([
            'is_admin' => true,
        ])->save();

        $properties = app(AnalyticsContext::class)
            ->eventProperties((int) $user->getKey());

        $this->assertTrue($properties['is_internal']);
        $this->assertSame(
            ['is_internal' => true],
            $properties['$set'],
        );
    }

    public function test_it_classifies_configured_qa_owner_without_database_lookup(): void
    {
        config([
            'services.posthog.internal_user_ids' => ['777'],
        ]);

        $properties = app(AnalyticsContext::class)
            ->eventProperties(777);

        $this->assertTrue($properties['is_internal']);
        $this->assertSame(
            ['is_internal' => true],
            $properties['$set'],
        );
    }

    public function test_it_classifies_regular_event_owner_external_without_an_authenticated_request(): void
    {
        config([
            'services.posthog.internal_user_ids' => [],
        ]);

        $user = User::factory()->create();

        $properties = app(AnalyticsContext::class)
            ->eventProperties((int) $user->getKey());

        $this->assertFalse($properties['is_internal']);
        $this->assertSame(
            ['is_internal' => false],
            $properties['$set'],
        );
    }

    protected function automaticallyVerifyAdminPasskey(): bool
    {
        return false;
    }
}
