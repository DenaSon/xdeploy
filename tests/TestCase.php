<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use App\Models\User;
use App\Support\Admin\AdminPasskeyVerificationSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\SSH\FakeSshPortReadinessProbe;

abstract class TestCase extends BaseTestCase
{
    private FakeSshPortReadinessProbe $sshPortReadinessProbe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sshPortReadinessProbe =
            new FakeSshPortReadinessProbe;

        $this->app->instance(
            SSHPortReadinessProbeInterface::class,
            $this->sshPortReadinessProbe,
        );
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if (
            $this->automaticallyVerifyAdminPasskey()
            && $user instanceof User
            && $user->isAdmin()
        ) {
            if (! $user->passkeys()->exists()) {
                $credentialId = rtrim(
                    strtr(
                        base64_encode('test-admin-'.$user->getKey()),
                        '+/',
                        '-_',
                    ),
                    '=',
                );

                $user->passkeys()->create([
                    'name' => 'Test admin passkey',
                    'credential_id' => $credentialId,
                    'credential' => [
                        'aaguid' => '00000000-0000-0000-0000-000000000000',
                    ],
                ]);
            }

            $this->withSession([
                AdminPasskeyVerificationSession::SESSION_KEY => [
                    'admin_user_id' => (int) $user->getKey(),
                    'verified_at' => now()->timestamp,
                ],
            ]);
        }

        return $this;
    }

    protected function sshPortReadinessProbe(): FakeSshPortReadinessProbe
    {
        return $this->sshPortReadinessProbe;
    }

    protected function automaticallyVerifyAdminPasskey(): bool
    {
        return true;
    }
}
