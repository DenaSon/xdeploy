<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Application\Authentication\Actions\LoginAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->create();

        $session = session();
        $session->setId(
            str_repeat('a', 40),
        );
        $session->start();

        $sessionIdBeforeLogin = $session->getId();

        app(LoginAction::class)->handle($user);

        self::assertAuthenticatedAs($user);
        self::assertNotSame(
            $sessionIdBeforeLogin,
            $session->getId(),
        );
    }
}
