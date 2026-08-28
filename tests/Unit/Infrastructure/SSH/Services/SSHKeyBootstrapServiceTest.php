<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SSH\Services;

use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\SSH\Exceptions\SSHKeyBootstrapException;
use App\Infrastructure\SSH\Services\SSHKeyBootstrapService;
use App\Models\Server;
use Tests\TestCase;

final class SSHKeyBootstrapServiceTest extends TestCase
{
    public function test_bootstrap_rejects_password_authenticated_server(): void
    {
        $server = $this->server(
            authenticationType: AuthenticationType::Password,
            username: 'root',
        );

        $this->expectException(
            SSHKeyBootstrapException::class,
        );

        app(SSHKeyBootstrapService::class)
            ->bootstrapToPassword(
                server: $server,
                newPassword: 'managed-password',
            );
    }

    public function test_bootstrap_requires_root_access(): void
    {
        $server = $this->server(
            authenticationType: AuthenticationType::SSHKey,
            username: 'ubuntu',
        );

        $this->expectException(
            SSHKeyBootstrapException::class,
        );

        app(SSHKeyBootstrapService::class)
            ->bootstrapToPassword(
                server: $server,
                newPassword: 'managed-password',
            );
    }

    public function test_bootstrap_requires_non_empty_new_password(): void
    {
        $server = $this->server(
            authenticationType: AuthenticationType::SSHKey,
            username: 'root',
        );

        $this->expectException(
            SSHKeyBootstrapException::class,
        );

        app(SSHKeyBootstrapService::class)
            ->bootstrapToPassword(
                server: $server,
                newPassword: '',
            );
    }

    public function test_bootstrap_requires_private_key_credential(): void
    {
        $server = $this->server(
            authenticationType: AuthenticationType::SSHKey,
            username: 'root',
        );

        $this->expectException(
            SSHKeyBootstrapException::class,
        );

        app(SSHKeyBootstrapService::class)
            ->bootstrapToPassword(
                server: $server,
                newPassword: 'managed-password',
            );
    }

    private function server(
        AuthenticationType $authenticationType,
        string $username,
    ): Server {
        $server = new Server;

        $server->forceFill([
            'authentication_type' => $authenticationType,
            'username' => $username,
        ]);

        return $server;
    }
}
