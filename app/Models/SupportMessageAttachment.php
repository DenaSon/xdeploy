<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportMessageAttachment extends Model
{
    protected $fillable = [
        'support_message_id',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sort_order',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<SupportMessage, $this>
     */
    public function supportMessage(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class);
    }
}
