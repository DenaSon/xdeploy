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

    public function test_profile_does_not_expose_manual_email_input(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('panel.profile'))
            ->assertOk()
            ->assertSee('افزودن ایمیل با Google')
            ->assertDontSee('wire:model="email"', false);
    }

    public function test_unverified_legacy_email_is_not_presented_as_account_email(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'email_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('panel.profile'))
            ->assertOk()
            ->assertSee('افزودن ایمیل با Google')
            ->assertDontSee('legacy@example.com');
    }

    public function test_verified_google_email_is_displayed_read_only(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('panel.profile'))
            ->assertOk()
            ->assertSee('verified@example.com')
            ->assertSee('تأیید شده با Google')
            ->assertDontSee('افزودن ایمیل با Google')
            ->assertDontSee('wire:model="email"', false);
    }

    public function test_saving_profile_does_not_mutate_email_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now()->subMinute(),
        ]);

        $persistedVerifiedAt = $user->email_verified_at;

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('firstName', 'محمد')
            ->set('lastName', 'اسدی')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame(
            'verified@example.com',
            $user->email,
        );
        $this->assertTrue(
            $user->email_verified_at?->equalTo(
                $persistedVerifiedAt,
            ) ?? false,
        );
    }
}
