<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\OrderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('type', 32)
                ->default(OrderType::Provisioning->value)
                ->after('user_id');

            $table->index(
                'type',
                'orders_type_index',
            );

            /*
             * Provisioning originally enforced a one-Order-per-Server
             * invariant through a unique server_id index. Renewal turns the
             * relation into one Server -> many historical Orders, while the
             * foreign key itself remains unchanged.
             *
             * Create a normal index before dropping the unique index so MySQL
             * always retains an index that can support the existing FK.
             */
            $table->index(
                'server_id',
                'orders_server_id_index',
            );

            $table->dropUnique(
                'orders_server_id_unique',
            );
        });
    }

    public function down(): void
    {
        /*
         * Once a Server has more than one historical Order, restoring the old
         * unique invariant would either fail cryptically at the database layer
         * or require deleting billing history. Refuse that destructive rollback
         * explicitly instead.
         */
        $duplicateServerOrderExists = DB::table('orders')
            ->whereNotNull('server_id')
            ->select('server_id')
            ->groupBy('server_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateServerOrderExists) {
            throw new RuntimeException(
                'Cannot rollback renewal support while a Server has multiple historical Orders.',
            );
        }

        Schema::table('orders', function (Blueprint $table): void {
            /*
             * Restore the unique index first so the existing Server FK remains
             * backed by an index when the temporary normal index is removed.
             */
            $table->unique(
                'server_id',
                'orders_server_id_unique',
            );

            $table->dropIndex(
                'orders_server_id_index',
            );

            $table->dropIndex(
                'orders_type_index',
            );

            $table->dropColumn(
                'type',
            );
        });
    }
};
