<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_name',
    'last_name',
])]
final class Profile extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): ?string
    {
        $fullName = trim(
            trim((string) $this->first_name)
            .' '
            .trim((string) $this->last_name),
        );

        return $fullName !== ''
            ? $fullName
            : null;
    }
}
