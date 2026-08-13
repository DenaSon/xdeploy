<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amnezia_wg_peers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('ip_address', 45);
            $table->string('public_key', 128)->nullable();
            $table->longText('client_config')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'ip_address']);
            $table->unique(['server_id', 'public_key']);
            $table->index(['server_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amnezia_wg_peers');
    }
};
