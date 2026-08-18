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
            'telegram_connections',
            static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->string('chat_id', 32)->unique();
                $table->string('telegram_user_id', 32)->unique();
                $table->string('username', 64)->nullable();
                $table->string('first_name')->nullable();
                $table->timestamp('connected_at');
                $table->timestamps();

                $table->unique('user_id');
            },
        );

        Schema::create(
            'telegram_link_challenges',
            static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index([
                    'user_id',
                    'consumed_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_challenges');
        Schema::dropIfExists('telegram_connections');
    }
};
