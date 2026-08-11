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
            'notification_deliveries',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->string(
                        'dedupe_key',
                        191,
                    )
                    ->unique();

                $table
                    ->string(
                        'notification_type',
                        191,
                    );

                $table
                    ->string(
                        'channel',
                        32,
                    )
                    ->default('database');

                $table
                    ->string(
                        'status',
                        32,
                    )
                    ->default('pending');

                $table
                    ->unsignedInteger('attempts')
                    ->default(0);

                $table
                    ->text('last_error')
                    ->nullable();

                $table
                    ->timestamp('delivered_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'status',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notification_deliveries',
        );
    }
};
