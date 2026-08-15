<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Support\Enums\SupportMessageAuthorRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = [
        'support_request_id',
        'author_id',
        'author_role',
        'body',
    ];

    protected $casts = [
        'author_role' => SupportMessageAuthorRole::class,
    ];

    /**
     * @return BelongsTo<SupportRequest, $this>
     */
    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    /**
     * The account that authored this message.
     *
     * The role is persisted separately so message history does not change
     * if that account's administrative role changes later.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'author_id',
        );
    }

    public function isFromAdmin(): bool
    {
        return $this->author_role
            === SupportMessageAuthorRole::Admin;
    }
}
