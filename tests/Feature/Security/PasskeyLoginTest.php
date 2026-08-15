<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Responses\Security\PasskeyConfirmationResponse;
use App\Http\Responses\Security\PasskeyLoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse as PasskeyConfirmationResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Tests\TestCase;

final class PasskeyLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost:8000',
            'passkeys.relying_party_id' => 'localhost',
            'passkeys.allowed_origins' => ['http://localhost:8000'],
            'passkeys.user_handle_secret' => 'testing-passkey-user-handle-secret',
        ]);
    }

    public function test_guest_can_request_passwordless_passkey_login_options(): void
    {
        $this->getJson(route('passkey.login-options'))
            ->assertOk()
            ->assertJsonStructure([
                'options' => [
                    'challenge',
                    'rpId',
                    'userVerification',
                ],
            ])
            ->assertJsonPath(
                'options.userVerification',
                'required',
            );
    }

    public function test_passkey_login_endpoint_is_exposed_only_to_guests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('passkey.login-options'))
            ->assertRedirect();
    }

    public function test_coreflare_overrides_package_passkey_security_responses(): void
    {
        $this->assertInstanceOf(
            PasskeyLoginResponse::class,
            app(PasskeyLoginResponseContract::class),
        );

        $this->assertInstanceOf(
            PasskeyConfirmationResponse::class,
            app(PasskeyConfirmationResponseContract::class),
        );
    }
}
