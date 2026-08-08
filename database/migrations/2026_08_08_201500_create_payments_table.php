<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('gateway', 32);

            // Canonical money snapshot. Current xDeploy commercial unit is IRR.
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IRR');

            $table->string('status', 32)
                ->default(PaymentStatus::Initiating->value);

            $table->string('gateway_reference', 191)
                ->nullable();

            $table->string('gateway_transaction_id', 191)
                ->nullable();

            $table->text('redirect_url')
                ->nullable();

            $table->string('failure_code', 100)
                ->nullable();

            $table->timestamp('verified_at')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['gateway', 'gateway_reference'],
                'payments_gateway_reference_unique',
            );

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'status']);
            $table->index('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
