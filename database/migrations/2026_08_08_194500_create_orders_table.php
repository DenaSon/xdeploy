<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Snapshot of the selected cloud infrastructure.
             */
            $table->string('region_id', 100);
            $table->string('size_id', 100);

            /*
             * Operating-system snapshot.
             */
            $table->string('image_id', 191);
            $table->string('image_name', 191);
            $table->string('image_distribution', 32);
            $table->string('image_version', 32);

            $table->unsignedInteger('default_disk_gib');
            $table->unsignedInteger('selected_disk_gib');

            /*
             * Snapshot of the xDeploy purchase period.
             */
            $table->string('period', 32);
            $table->unsignedInteger('duration_hours');

            /*
             * Monetary values are stored in IRR.
             */
            $table->unsignedBigInteger('provider_cost');
            $table->unsignedSmallInteger('markup_percent');
            $table->unsignedBigInteger('final_amount');

            $table->char('currency', 3)
                ->default('IRR');

            $table->string('status', 32)
                ->default(
                    OrderStatus::PendingPayment->value,
                );

            $table->timestamp('quote_expires_at');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);

            $table->index([
                'status',
                'quote_expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
