<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Billing\DTOs\PurchasePriceData;
use DomainException;

final class PurchaseQuoteChangedException extends DomainException
{
    public function __construct(
        public readonly PurchasePriceData $currentQuote,
    ) {
        parent::__construct(
            'The purchase quote changed before order creation.',
        );
    }
}
