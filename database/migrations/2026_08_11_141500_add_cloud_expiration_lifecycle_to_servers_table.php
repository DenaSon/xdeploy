<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'servers',
            function (Blueprint $table): void {
                $table
                    ->timestamp('expires_at')
                    ->nullable()
                    ->after('provisioned_at');

                $table
                    ->timestamp('termination_started_at')
                    ->nullable()
                    ->after('expires_at');

                $table
                    ->timestamp('termination_last_attempt_at')
                    ->nullable()
                    ->after('termination_started_at');

                $table
                    ->unsignedInteger('termination_attempts')
                    ->default(0)
                    ->after('termination_last_attempt_at');

                $table
                    ->text('termination_last_error')
                    ->nullable()
                    ->after('termination_attempts');

                $table
                    ->timestamp('terminated_at')
                    ->nullable()
                    ->after('termination_last_error');

                $table->index(
                    'expires_at',
                    'servers_expires_at_index',
                );
            },
        );

        $this->backfillExistingFulfilledCloudServers();
    }

    public function down(): void
    {
        Schema::table(
            'servers',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'servers_expires_at_index',
                );

                $table->dropColumn([
                    'expires_at',
                    'termination_started_at',
                    'termination_last_attempt_at',
                    'termination_attempts',
                    'termination_last_error',
                    'terminated_at',
                ]);
            },
        );
    }

    private function backfillExistingFulfilledCloudServers(): void
    {
        DB::table('orders')
            ->where(
                'status',
                'fulfilled',
            )
            ->whereNotNull(
                'server_id',
            )
            ->whereNotNull(
                'duration_hours',
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($orders): void {
                    foreach ($orders as $order) {
                        $durationHours =
                            (int) $order->duration_hours;

                        if ($durationHours < 1) {
                            continue;
                        }

                        $server = DB::table('servers')
                            ->where(
                                'id',
                                $order->server_id,
                            )
                            ->whereNotNull(
                                'cloud_provider',
                            )
                            ->whereNotNull(
                                'cloud_server_id',
                            )
                            ->whereNotNull(
                                'provisioned_at',
                            )
                            ->whereNull(
                                'expires_at',
                            )
                            ->first([
                                'id',
                                'provisioned_at',
                            ]);

                        if ($server === null) {
                            continue;
                        }

                        $expiresAt = CarbonImmutable::parse(
                            (string) $server->provisioned_at,
                        )->addHours(
                            $durationHours,
                        );

                        DB::table('servers')
                            ->where(
                                'id',
                                $server->id,
                            )
                            ->whereNull(
                                'expires_at',
                            )
                            ->update([
                                'expires_at' => $expiresAt,
                            ]);
                    }
                },
            );
    }
};
