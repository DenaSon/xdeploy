<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowBackgroundOperationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_install_operation_renders_polling_progress_without_lifecycle_actions(): void
    {
        $user = User::query()->create([
            'name' => 'Background Operation View Test',
            'phone' => '09120000021',
        ]);

        $server = $user->servers()->create([
            'name' => 'Background Operation View Server',
            'host' => '192.0.2.21',
            'port' => 22,
            'username' => 'root',
        ]);

        $this->actingAs($user);

        $html = view(
            'livewire.applications.show',
            [
                'server' => $server,
                'serverId' => (int) $server->getKey(),
                'application' => 'n8n',
                'name' => 'n8n',
                'shortDescription' => 'Workflow automation',
                'description' => null,
                'icon' => 'lucide.workflow',
                'managementPanel' => 'applications.n8n.management',
                'managementPanelRevision' => 0,
                'info' => [
                    'state' => 'unknown',
                    'version' => null,
                    'is_installed' => false,
                    'is_running' => false,
                    'is_not_installed' => false,
                    'is_unknown' => true,
                ],
                'operationType' => 'install',
                'operationStatus' => 'pending',
                'operationActive' => true,
                'sshUnavailable' => false,
                'sshErrorMessage' => null,
                'sshRetryAfter' => null,
                'successMessage' => null,
                'errorMessage' => null,
            ],
        )->render();

        self::assertStringContainsString(
            'wire:poll.2s="pollOperation"',
            $html,
        );

        self::assertStringContainsString(
            'در انتظار نصب',
            $html,
        );

        self::assertStringContainsString(
            'فرآیند در پس‌زمینه ادامه خواهد یافت.',
            $html,
        );

        self::assertStringNotContainsString(
            'wire:click="install"',
            $html,
        );

        self::assertStringNotContainsString(
            'application-management-panel-',
            $html,
        );
    }
}
