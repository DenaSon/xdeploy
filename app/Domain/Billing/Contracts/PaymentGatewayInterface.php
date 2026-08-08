<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\PaymentInitiationData;
use App\Domain\Billing\DTOs\PaymentInitiationRequestData;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\DTOs\PaymentVerificationRequestData;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function initiate(
        PaymentInitiationRequestData $request,
    ): PaymentInitiationData;

    public function verify(
        PaymentVerificationRequestData $request,
    ): PaymentVerificationData;
}
