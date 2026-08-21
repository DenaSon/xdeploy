<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class AdminLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_viewer_is_mounted_under_admin_namespace(): void
    {
        $this->assertSame(
            url('/admin/logs'),
            route('log-viewer.index'),
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('log-viewer.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_non_admin_cannot_access_log_viewer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('log-viewer.index'))
            ->assertForbidden();
    }

    public function test_verified_admin_can_access_log_viewer(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('log-viewer.index'))
            ->assertOk();
    }

    public function test_admin_can_download_but_cannot_delete_logs_from_web_ui(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->assertTrue(
            Gate::forUser($admin)->allows('downloadLogFile'),
        );
        $this->assertTrue(
            Gate::forUser($admin)->allows('downloadLogFolder'),
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('deleteLogFile'),
        );
        $this->assertFalse(
            Gate::forUser($admin)->allows('deleteLogFolder'),
        );
    }
}
