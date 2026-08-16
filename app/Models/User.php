<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'phone',
    'email',
])]
class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use PasskeyAuthenticatable;

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'author_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function displayName(): ?string
    {
        return $this->profile?->fullName();
    }

    public function getNameAttribute(): ?string
    {
        return $this->displayName();
    }

    public function scopeMatchesIdentity(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('profile', function (Builder $profileQuery) use ($search): void {
                    $profileQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        });
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function getPasskeyDisplayName(): string
    {
        return $this->displayName() ?? 'کاربر '.config('app.name');
    }

    public function getPasskeyUsername(): string
    {
        return (string) $this->phone;
    }

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }
}
