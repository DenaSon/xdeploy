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

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function displayName(): ?string
    {
        return $this->profile?->fullName();
    }

    /**
     * Backward-compatible read alias for presentation code.
     * Persisted personal names live exclusively in profiles.
     */
    public function getNameAttribute(): ?string
    {
        return $this->displayName();
    }

    /**
     * Scope users by phone or profile name.
     *
     * @param Builder<User> $query
     * @return Builder<User>
     */
    public function scopeMatchesIdentity(
        Builder $query,
        string $search,
    ): Builder {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $terms = preg_split(
            '/\s+/u',
            $search,
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        return $query->where(
            function (Builder $query) use ($search, $terms): void {
                $query
                    ->where('phone', 'like', "%{$search}%")
                    ->orWhereHas(
                        'profile',
                        function (Builder $profileQuery) use ($terms): void {
                            foreach ($terms as $term) {
                                $profileQuery->where(
                                    function (Builder $termQuery) use ($term): void {
                                        $termQuery
                                            ->where('first_name', 'like', "%{$term}%")
                                            ->orWhere('last_name', 'like', "%{$term}%");
                                    },
                                );
                            }
                        },
                    );
            },
        );
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function getPasskeyDisplayName(): string
    {
        return $this->displayName()
            ?? 'کاربر '.config('app.name');
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
