<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'phone',
])]
class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use PasskeyAuthenticatable;

    /**
     * User owned servers.
     *
     * @return HasMany<Server, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function getPasskeyDisplayName(): string
    {
        $name = trim((string) $this->name);

        return $name !== ''
            ? $name
            : 'کاربر '.config('app.name');
    }

    public function getPasskeyUsername(): string
    {
        $phone = (string) $this->phone;

        if (mb_strlen($phone) < 7) {
            return 'حساب '.config('app.name');
        }

        return mb_substr($phone, 0, 3)
            .'•••••'
            .mb_substr($phone, -3);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }
}
