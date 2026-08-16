<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GoogleEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_oidc.client_id' => 'google-client-id',
            'services.google_oidc.client_secret' => 'google-client-secret',
            'services.google_oidc.authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'services.google_oidc.token_endpoint' => 'https://oauth2.googleapis.com/token',
            'services.google_oidc.userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'services.google_oidc.connect_timeout' => 5,
            'services.google_oidc.timeout' => 10,
        ]);
    }

    public function test_google_verification_requires_authentication(): void
    {
        $this->get(
            route('panel.profile.email.google.redirect'),
        )->assertRedirect(route('login'));

        $this->get(
            route(
                'panel.profile.email.google.callback',
                [
                    'state' => 'state',
                    'code' => 'code',
                    'iss' => 'https://accounts.google.com',
                ],
            ),
        )->assertRedirect(route('login'));
    }

    public function test_redirect_starts_bound_google_openid_attempt(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(
                route('panel.profile.email.google.redirect'),
            )
            ->assertRedirect();

        $query = $this->redirectQuery($response->headers->get('Location'));

        $this->assertSame(
            'google-client-id',
            $query['client_id'] ?? null,
        );
        $this->assertSame(
            'code',
            $query['response_type'] ?? null,
        );
        $this->assertSame(
            'openid email',
            $query['scope'] ?? null,
        );
        $this->assertSame(
            route('panel.profile.email.google.callback'),
            $query['redirect_uri'] ?? null,
        );
        $this->assertNotEmpty($query['state'] ?? null);

        $attempt = session(
            'profile.google_email_verification',
        );

        $this->assertIsArray($attempt);
        $this->assertSame(
            $user->id,
            $attempt['user_id'] ?? null,
        );
        $this->assertSame(
            'user@example.com',
            $attempt['expected_email'] ?? null,
        );
        $this->assertSame(
            hash(
                'sha256',
                (string) $query['state'],
            ),
            $attempt['state_hash'] ?? null,
        );
        $this->assertArrayNotHasKey(
            'state',
            $attempt,
        );
    }

    public function test_matching_verified_google_email_marks_saved_email_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'user@example.com',
            verified: true,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHas(
                'profile_status',
                'ایمیل با موفقیت از طریق Google تأیید شد.',
            );

        $user->refresh();

        $this->assertNotNull(
            $user->email_verified_at,
        );

        Http::assertSentCount(2);
    }

    public function test_verified_google_email_is_added_when_account_email_is_empty(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'New.User@Example.COM',
            verified: true,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'));

        $user->refresh();

        $this->assertSame(
            'new.user@example.com',
            $user->email,
        );
        $this->assertNotNull(
            $user->email_verified_at,
        );
    }

    public function test_mismatched_google_email_never_changes_saved_email(): void
    {
        $user = User::factory()->create([
            'email' => 'expected@example.com',
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'other@example.com',
            verified: true,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHasErrors('email');

        $user->refresh();

        $this->assertSame(
            'expected@example.com',
            $user->email,
        );
        $this->assertNull(
            $user->email_verified_at,
        );
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'user@example.com',
            verified: false,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHas(
                'profile_error',
                'Google مالکیت این ایمیل را تأیید نکرده است.',
            );

        $this->assertNull(
            $user->fresh()->email_verified_at,
        );
    }

    public function test_invalid_state_is_rejected_before_google_request(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $this->startAttempt($user);

        Http::fake();

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery('invalid-state'),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHas(
                'profile_error',
                'درخواست تأیید ایمیل معتبر نیست یا منقضی شده است.',
            );

        Http::assertNothingSent();
    }

    public function test_email_changed_during_google_round_trip_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'first@example.com',
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $user->forceFill([
            'email' => 'second@example.com',
        ])->save();

        $this->fakeGoogleIdentity(
            email: 'first@example.com',
            verified: true,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHasErrors('email');

        $user->refresh();

        $this->assertSame(
            'second@example.com',
            $user->email,
        );
        $this->assertNull(
            $user->email_verified_at,
        );
    }

    public function test_google_email_cannot_be_claimed_by_second_account(): void
    {
        User::factory()->create([
            'email' => 'used@example.com',
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'used@example.com',
            verified: true,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'panel.profile.email.google.callback',
                    $this->callbackQuery($state),
                ),
            )
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHasErrors('email');

        $this->assertNull(
            $user->fresh()->email,
        );
    }

    public function test_callback_consumes_attempt_once(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => null,
        ]);

        $state = $this->startAttempt($user);

        $this->fakeGoogleIdentity(
            email: 'user@example.com',
            verified: true,
        );

        $callback = route(
            'panel.profile.email.google.callback',
            $this->callbackQuery($state),
        );

        $this->actingAs($user)
            ->get($callback)
            ->assertRedirect(route('panel.profile'));

        Http::fake();

        $this->actingAs($user)
            ->get($callback)
            ->assertRedirect(route('panel.profile'))
            ->assertSessionHas(
                'profile_error',
                'درخواست تأیید ایمیل معتبر نیست یا منقضی شده است.',
            );

        Http::assertNothingSent();
    }

    private function startAttempt(User $user): string
    {
        $response = $this->actingAs($user)
            ->get(
                route('panel.profile.email.google.redirect'),
            )
            ->assertRedirect();

        $query = $this->redirectQuery(
            $response->headers->get('Location'),
        );

        $state = $query['state'] ?? null;

        $this->assertIsString($state);
        $this->assertNotSame('', $state);

        return $state;
    }

    /**
     * @return array<string, string>
     */
    private function callbackQuery(string $state): array
    {
        return [
            'state' => $state,
            'code' => 'authorization-code',
            'iss' => 'https://accounts.google.com',
        ];
    }

    private function fakeGoogleIdentity(
        string $email,
        bool $verified,
    ): void {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(
                [
                    'access_token' => 'short-lived-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ],
                200,
            ),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(
                [
                    'sub' => 'google-subject-123',
                    'email' => $email,
                    'email_verified' => $verified,
                ],
                200,
            ),
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @return array<string, string>
     */
    private function redirectQuery(?string $location): array
    {
        $this->assertIsString($location);

        $queryString = parse_url(
            $location,
            PHP_URL_QUERY,
        );

        $this->assertIsString($queryString);

        parse_str(
            $queryString,
            $query,
        );

        /** @var array<string, string> $query */
        return $query;
    }
}
