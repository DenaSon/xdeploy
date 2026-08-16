<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Application\User\Actions\UpdateEmailAction;
use App\Application\User\Actions\UpdateProfileAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('پروفایل')]
final class Edit extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $user = $this->user();
        $profile = $user->profile;

        $this->firstName = (string) $profile?->first_name;
        $this->lastName = (string) $profile?->last_name;
        $this->email = (string) $user->email;
    }

    public function save(
        UpdateProfileAction $updateProfile,
        UpdateEmailAction $updateEmail,
    ): void {
        $user = $this->user();

        $this->email = mb_strtolower(
            trim($this->email),
        );

        $validated = $this->validate([
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')
                    ->ignore($user->getKey()),
            ],
        ]);

        $this->firstName = trim(
            (string) ($validated['firstName'] ?? ''),
        );
        $this->lastName = trim(
            (string) ($validated['lastName'] ?? ''),
        );
        $this->email = (string) ($validated['email'] ?? '');

        $updateEmail->handle(
            user: $user,
            email: $this->email !== ''
                ? $this->email
                : null,
        );

        $updateProfile->handle(
            user: $user,
            firstName: $this->firstName,
            lastName: $this->lastName,
        );

        $this->email = (string) $user->email;

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
