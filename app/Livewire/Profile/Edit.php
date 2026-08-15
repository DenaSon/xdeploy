<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Application\User\Actions\UpdateProfileAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('پروفایل')]
final class Edit extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $profile = $this->user()->profile;

        $this->firstName = (string) $profile?->first_name;
        $this->lastName = (string) $profile?->last_name;
    }

    public function save(
        UpdateProfileAction $updateProfile,
    ): void {
        $validated = $this->validate([
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
        ]);

        $this->firstName = trim((string) ($validated['firstName'] ?? ''));
        $this->lastName = trim((string) ($validated['lastName'] ?? ''));

        $updateProfile->handle(
            user: $this->user(),
            firstName: $this->firstName,
            lastName: $this->lastName,
        );

        $this->resetValidation();
        $this->statusMessage = 'پروفایل با موفقیت ذخیره شد.';
    }

    public function render(): View
    {
        return view(
            'livewire.profile.edit',
            ['user' => $this->user()],
        );
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
