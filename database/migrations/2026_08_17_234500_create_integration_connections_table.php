<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'integration_connections',
            static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->string('provider', 50);
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->json('scopes');
                $table->timestamp('access_token_expires_at')->nullable();
                $table->timestamp('connected_at');
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique([
                    'user_id',
                    'provider',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
