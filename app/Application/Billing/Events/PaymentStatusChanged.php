<?php

declare(strict_types=1);

namespace App\Application\Billing\Events;

use App\Domain\Billing\Enums\PaymentStatus;

final readonly class PaymentStatusChanged
{
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public PaymentStatus $status,
    ) {}

    public function dedupeKey(): string
    {
        return sprintf(
            'billing:payment:%d:%s',
            $this->paymentId,
            $this->status->value,
        );
    }
}
