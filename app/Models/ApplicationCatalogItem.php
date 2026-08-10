<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplicationCatalogItem extends Model
{
    protected $table = 'applications';

    protected $fillable = [
        'slug',
        'name',
        'short_description',
        'description',
        'icon',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopePublished(
        Builder $query,
    ): void {
        $query->where(
            'is_published',
            true,
        );
    }

    public function scopeOrdered(
        Builder $query,
    ): void {
        $query
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
