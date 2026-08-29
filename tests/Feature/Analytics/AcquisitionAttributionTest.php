<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Infrastructure\Analytics\AcquisitionAttribution;
use App\Infrastructure\Analytics\AnalyticsContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class AcquisitionAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get(
            '/_testing/analytics-attribution',
            static function (AcquisitionAttribution $attribution) {
                return response()->json([
                    'first' => $attribution->firstTouch(),
                    'last' => $attribution->lastTouch(),
                ]);
            },
        );

        Route::middleware('web')->get(
            '/_testing/analytics-context/{user}',
            static function (User $user, AnalyticsContext $context) {
                Auth::login($user);

                return response()->json(
                    $context->eventProperties($user->getKey()),
                );
            },
        );
    }

    public function test_first_touch_is_immutable_and_last_touch_updates_only_on_new_utm_visit(): void
    {
        $this->get(
            '/_testing/analytics-attribution'
            .'?utm_source=instagram'
            .'&utm_medium=social'
            .'&utm_campaign=launch',
        )->assertOk()->assertJson([
            'first' => [
                'source' => 'instagram',
                'medium' => 'social',
                'campaign' => 'launch',
            ],
            'last' => [
                'source' => 'instagram',
                'medium' => 'social',
                'campaign' => 'launch',
            ],
        ]);

        $this->get(
            '/_testing/analytics-attribution',
        )->assertOk()->assertJson([
            'first' => [
                'source' => 'instagram',
                'medium' => 'social',
                'campaign' => 'launch',
            ],
            'last' => [
                'source' => 'instagram',
                'medium' => 'social',
                'campaign' => 'launch',
            ],
        ]);

        $this->get(
            '/_testing/analytics-attribution'
            .'?utm_source=telegram'
            .'&utm_medium=community'
            .'&utm_campaign=follow-up',
        )->assertOk()->assertJson([
            'first' => [
                'source' => 'instagram',
                'medium' => 'social',
                'campaign' => 'launch',
            ],
            'last' => [
                'source' => 'telegram',
                'medium' => 'community',
                'campaign' => 'follow-up',
            ],
        ]);
    }

    public function test_only_allowlisted_normalized_utm_values_are_persisted(): void
    {
        $longCampaign = str_repeat('x', 200);

        $this->get(
            '/_testing/analytics-attribution'
            .'?utm_source=%3Cscript%3Egoogle%3C%2Fscript%3E'
            .'&utm_campaign='.$longCampaign
            .'&email=customer%40example.com',
        )->assertOk()->assertJson([
            'first' => [
                'source' => 'google',
                'campaign' => str_repeat('x', 160),
            ],
        ]);

        $this->assertSame(
            [
                'source' => 'google',
                'campaign' => str_repeat('x', 160),
            ],
            session()->get(
                AcquisitionAttribution::FIRST_TOUCH_SESSION_KEY,
            ),
        );
    }

    public function test_malformed_utm_input_is_ignored_without_breaking_the_request(): void
    {
        $this->get(
            '/_testing/analytics-attribution?utm_source[]=telegram',
        )->assertOk()->assertJson([
            'first' => [],
            'last' => [],
        ]);
    }

    public function test_authenticated_backend_context_receives_event_and_person_attribution(): void
    {
        $user = User::factory()->create();

        $response = $this->get(
            '/_testing/analytics-context/'.$user->getKey()
            .'?utm_source=instagram'
            .'&utm_medium=social'
            .'&utm_campaign=launch',
        )->assertOk();

        $response->assertJsonPath(
            'first_touch_source',
            'instagram',
        );
        $response->assertJsonPath(
            'last_touch_source',
            'instagram',
        );
        $response->assertJsonPath(
            '$set.last_touch_campaign',
            'launch',
        );
        $response->assertJsonPath(
            '$set_once.first_touch_campaign',
            'launch',
        );
        $response->assertJsonPath(
            '$set.is_internal',
            false,
        );
    }
}
