<?php

declare(strict_types=1);

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // Ownership
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Server Information
            $table->string('name');
            $table->string('host');

            $table->unsignedSmallInteger('port')
                ->default(22);

            // Authentication
            $table->string('username');

            $table->enum('authentication_type', array_column(
                AuthenticationType::cases(),
                'value'
            ))->default(AuthenticationType::Password->value);

            $table->text('credential')
                ->nullable();

            $table->string('private_key_path')
                ->nullable();

            // Status
            $table->enum('status', array_column(
                ServerStatus::cases(),
                'value'
            ))->default(ServerStatus::Active->value);

            $table->timestamps();

            // Optional: prevent duplicate server registration per user
            $table->unique([
                'user_id',
                'host',
                'port',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
