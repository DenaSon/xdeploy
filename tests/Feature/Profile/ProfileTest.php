<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_requires_authentication(): void
    {
        $this->get(route('panel.profile'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_optional_profile_page(): void
    {
        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $this->actingAs($user)
            ->get(route('panel.profile'))
            ->assertOk()
            ->assertSee('پروفایل')
            ->assertSee('09123456789')
            ->assertSee('تکمیل پروفایل اختیاری است');
    }

    public function test_user_can_create_and_update_profile(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('firstName', '  محمد  ')
            ->set('lastName', '  اسدی  ')
            ->call('save')
            ->assertSet('firstName', 'محمد')
            ->assertSet('lastName', 'اسدی')
            ->assertSet('statusMessage', 'پروفایل با موفقیت ذخیره شد.');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'محمد',
            'last_name' => 'اسدی',
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('firstName', 'محمدرضا')
            ->call('save');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'محمدرضا',
            'last_name' => 'اسدی',
        ]);
    }

    public function test_empty_profile_is_not_persisted(): void
    {
        $user = User::factory()->create();

        $user->profile()->create([
            'first_name' => 'محمد',
            'last_name' => 'اسدی',
        ]);

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('firstName', '')
            ->set('lastName', '')
            ->call('save');

        $this->assertDatabaseMissing('profiles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_profile_names_are_limited_to_eighty_characters(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Edit::class)
            ->set('firstName', str_repeat('ا', 81))
            ->call('save')
            ->assertHasErrors(['firstName' => 'max']);
    }

    public function test_profile_name_is_used_for_display_and_passkeys(): void
    {
        $user = User::factory()->create();

        $user->profile()->create([
            'first_name' => 'محمد',
            'last_name' => 'اسدی',
        ]);

        $user = $user->fresh();

        $this->assertSame('محمد اسدی', $user->displayName());
        $this->assertSame('محمد اسدی', $user->name);
        $this->assertSame('محمد اسدی', $user->getPasskeyDisplayName());
    }

    public function test_passkey_display_name_falls_back_when_profile_is_empty(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            'کاربر '.config('app.name'),
            $user->getPasskeyDisplayName(),
        );
    }
}
