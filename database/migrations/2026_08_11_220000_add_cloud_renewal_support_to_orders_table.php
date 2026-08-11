<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\OrderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('type', 32)
                ->default(OrderType::CloudPurchase->value)
                ->after('id');

            /*
             * Keep a normal index before removing the historical unique
             * index because server_id remains a foreign-key column.
             */
            $table->index(
                'server_id',
                'orders_server_id_index',
            );
        });

        Schema::table('orders', function (Blueprint $table): void {
            /*
             * A Server may now have one purchase Order and many renewal
             * Orders. The previous one-Order-per-Server invariant no longer
             * represents the commercial lifecycle.
             */
            $table->dropUnique(
                'orders_server_id_unique',
            );

            $table->index(
                [
                    'server_id',
                    'type',
                    'status',
                ],
                'orders_server_type_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(
                'orders_server_type_status_index',
            );

            /*
             * Rollback is only safe when no Server has accumulated multiple
             * Orders after this migration was applied.
             */
            $table->unique(
                'server_id',
                'orders_server_id_unique',
            );

            $table->dropIndex(
                'orders_server_id_index',
            );

            $table->dropColumn('type');
        });
    }
};
