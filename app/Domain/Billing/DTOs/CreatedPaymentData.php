<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class CreatedPaymentData
{
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public string $gateway,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $redirectUrl,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'order_id' => $this->orderId,
            'gateway' => $this->gateway,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'redirect_url' => $this->redirectUrl,
        ];
    }
}
