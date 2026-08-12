<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_endpoints', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('server_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string(
                'application_type',
                64,
            );

            $table
                ->string(
                    'domain',
                    253,
                )
                ->charset('ascii')
                ->collation('ascii_general_ci');

            $table
                ->timestamp('activated_at')
                ->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'server_id',
                    'application_type',
                ],
                'public_endpoints_server_application_unique',
            );

            $table->unique(
                [
                    'server_id',
                    'domain',
                ],
                'public_endpoints_server_domain_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'public_endpoints',
        );
    }
};
