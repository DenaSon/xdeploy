<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            /*
             * Cloud Server is persisted immediately after Provider Create.
             * At that moment the final connection IP may not exist yet.
             */
            $table
                ->string('host')
                ->nullable()
                ->change();

            $table
                ->string('cloud_provider', 50)
                ->nullable()
                ->after('status');

            /*
             * Provider identifiers are intentionally strings.
             * A future provider may not use UUID identifiers.
             */
            $table
                ->string('cloud_server_id', 191)
                ->nullable()
                ->after('cloud_provider');

            $table
                ->string('cloud_region', 100)
                ->nullable()
                ->after('cloud_server_id');

            $table
                ->timestamp('provisioned_at')
                ->nullable()
                ->after('cloud_region');

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
        /*
         * A host cannot become NOT NULL while unfinished cloud records exist.
         * Rollback should fail clearly instead of silently deleting records.
         */
        if (
            DB::table('servers')
                ->whereNull('host')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot rollback cloud provisioning fields while servers with a null host exist.',
            );
        }

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropUnique(
                'servers_cloud_provider_server_unique',
            );

            $table->dropColumn([
                'cloud_provider',
                'cloud_server_id',
                'cloud_region',
                'provisioned_at',
            ]);

            $table
                ->string('host')
                ->nullable(false)
                ->change();
        });
    }
};
