<?php

namespace App\Domain\Server\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'authentication_type',
        'credential',
        'private_key_path',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];
}
