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
        Schema::create('servers', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name')
                ->nullable();

            $table->string('host')
                ->nullable();

            $table->unsignedSmallInteger('port')
                ->default(22);

            $table->string('username');

            $table->enum('authentication_type', array_column(
                AuthenticationType::cases(),
                'value',
            ))->default(AuthenticationType::Password->value);

            $table->text('credential')
                ->nullable();

            $table->uuid('credential_context')
                ->nullable()
                ->unique();

            $table->enum('status', array_column(
                ServerStatus::cases(),
                'value',
            ))->default(ServerStatus::Active->value);

            $table->string('cloud_provider', 50)
                ->nullable();

            $table->string('cloud_server_id', 191)
                ->nullable();

            $table->string('cloud_region', 100)
                ->nullable();

            $table->timestamp('provisioned_at')
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->timestamp('termination_started_at')
                ->nullable();

            $table->timestamp('termination_last_attempt_at')
                ->nullable();

            $table->unsignedInteger('termination_attempts')
                ->default(0);

            $table->text('termination_last_error')
                ->nullable();

            $table->timestamp('terminated_at')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');

            $table->index(
                'expires_at',
                'servers_expires_at_index',
            );

            $table->unique([
                'user_id',
                'host',
                'port',
            ]);

            $table->unique(
                [
                    'cloud_provider',
                    'cloud_server_id',
                ],
                'servers_cloud_provider_server_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
