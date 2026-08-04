<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProvisionCloudServerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'cloud.default' => 'arvan',

            'cloud.providers.arvan.region' => 'eu-west1-a',

            'cloud.providers.arvan.defaults.size_id' => 'eco-1-1-0',

            'cloud.providers.arvan.defaults.image_id' => 'ubuntu-image-id',

            'cloud.providers.arvan.defaults.network_id' => 'network-id',

            'cloud.providers.arvan.defaults.security_group_id' => 'security-group-id',

            'cloud.providers.arvan.defaults.disk_size' => 25,

            'cloud.providers.arvan.defaults.init_script' => '',

            'cloud.providers.arvan.defaults.ha_enabled' => false,
        ]);
    }

    public function test_it_requires_the_explicit_execute_flag(): void
    {
        $exitCode = Artisan::call(
            'cloud:provision-server',
            [
                'user' => 1,
            ],
        );

        $output = Artisan::output();

        $this->assertSame(
            Command::INVALID,
            $exitCode,
        );

        $this->assertStringContainsString(
            'Re-run it with --execute.',
            $output,
        );

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_unknown_user_without_contacting_provider(): void
    {
        $exitCode = Artisan::call(
            'cloud:provision-server',
            [
                'user' => 999999,
                '--execute' => true,
            ],
        );

        $output = Artisan::output();

        $this->assertSame(
            Command::FAILURE,
            $exitCode,
        );

        $this->assertStringContainsString(
            'xDeploy user [999999] was not found.',
            $output,
        );

        Http::assertNothingSent();
    }

    public function test_it_does_not_create_a_server_when_confirmation_is_declined(): void
    {
        $user = $this->createUser();

        $this->artisan(
            'cloud:provision-server',
            [
                'user' => $user->id,
                '--execute' => true,
            ],
        )
            ->expectsConfirmation(
                'Create this billable cloud server now?',
                'no',
            )
            ->expectsOutputToContain(
                'Provisioning cancelled.',
            )
            ->assertExitCode(
                Command::SUCCESS,
            );

        $this->assertDatabaseCount(
            'servers',
            0,
        );

        Http::assertNothingSent();
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Cloud E2E User',

            'phone' => '+4915112345678',
        ]);
    }
}
