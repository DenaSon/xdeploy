<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ProfileEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('email', '  User@Example.COM  ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('email', 'user@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'user@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_changing_email_clears_previous_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('email', 'new@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame(
            'new@example.com',
            $user->email,
        );
        $this->assertNull(
            $user->email_verified_at,
        );
    }

    public function test_saving_same_email_keeps_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now()->subMinute(),
        ]);

        $user->refresh();
        $persistedVerifiedAt = $user->email_verified_at;

        $this->assertNotNull($persistedVerifiedAt);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('email', ' User@Example.COM ')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame(
            'user@example.com',
            $user->email,
        );
        $this->assertTrue(
            $user->email_verified_at?->equalTo(
                $persistedVerifiedAt,
            ) ?? false,
        );
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'used@example.com',
        ]);

        $user = User::factory()->create([
            'email' => null,
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('email', 'used@example.com')
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_user_can_remove_optional_email(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_verified_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('email', '')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
    }
}
