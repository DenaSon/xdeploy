<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['phone'])]
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected function casts(): array
    {
        return [];
    }
}
