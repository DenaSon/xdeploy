<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/**
 * Stable customer-facing lifecycle states derived from the persisted Order
 * state plus its latest payment attempt.
 *
 * UI copy and visual styling intentionally stay outside this enum.
 */
enum CustomerOrderState: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentPending = 'payment_pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case NeedsAttention = 'needs_attention';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
