<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_endpoint_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_endpoint_id')
                ->constrained('public_endpoints')
                ->cascadeOnDelete();
            $table->string('application_type', 64);
            $table->string('domain', 253);
            $table->string('operation', 32);
            $table->string('status', 32)->default('pending');
            $table->string('failure_code', 128)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'server_id', 'application_type', 'status'],
                'public_endpoint_operations_target_status_index',
            );
            $table->index(
                ['public_endpoint_id', 'status'],
                'public_endpoint_operations_endpoint_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_endpoint_operations');
    }
};
