<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_operations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('server_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('application_type', 64);
            $table->string('operation', 32);
            $table->string('status', 32);

            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(
                [
                    'server_id',
                    'application_type',
                    'status',
                ],
                'application_operations_target_status_index',
            );

            $table->index(
                [
                    'user_id',
                    'server_id',
                    'application_type',
                ],
                'application_operations_owner_target_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_operations');
    }
};
