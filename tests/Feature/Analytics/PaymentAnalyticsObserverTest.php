<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Observers\PaymentAnalyticsObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentAnalyticsObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_payment_emits_started_with_purchase_context(): void
    {
        [$order, $payment] = $this->payment(
            initialStatus: PaymentStatus::Initiating,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Pending,
            analytics: $analytics,
        );

        $event = $analytics->sole();

        $this->assertSame(
            ProductAnalyticsEvent::PaymentStarted,
            $event['event'],
        );

        $this->assertSame(
            $order->user_id,
            $event['user_id'],
        );

        $this->assertSame(
            'purchase',
            $event['properties']['source'],
        );

        $this->assertSame(
            'vps',
            $event['properties']['resource_kind'],
        );

        $this->assertSame(
            14,
            $event['properties']['duration_days'],
        );

        $this->assertSame(
            1,
            $event['properties']['attempt_number'],
        );
    }

    public function test_paid_payment_emits_succeeded_once_for_the_status_transition(): void
    {
        [, $payment] = $this->payment(
            initialStatus: PaymentStatus::Pending,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Paid,
            analytics: $analytics,
        );

        $this->assertSame(
            ProductAnalyticsEvent::PaymentSucceeded,
            $analytics->sole()['event'],
        );

        $observer = new PaymentAnalyticsObserver(
            $analytics,
        );

        $observer->updated(
            $payment->fresh(),
        );

        $this->assertCount(
            1,
            $analytics->events,
        );
    }

    public function test_failed_payment_emits_failed_with_safe_failure_code(): void
    {
        [, $payment] = $this->payment(
            initialStatus: PaymentStatus::Initiating,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Failed,
            analytics: $analytics,
            failureCode: 'initiation_failed',
        );

        $event = $analytics->sole();

        $this->assertSame(
            ProductAnalyticsEvent::PaymentFailed,
            $event['event'],
        );

        $this->assertSame(
            'initiation_failed',
            $event['properties']['failure_code'],
        );
    }

    public function test_failed_payment_drops_unstructured_failure_text(): void
    {
        [, $payment] = $this->payment(
            initialStatus: PaymentStatus::Initiating,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Failed,
            analytics: $analytics,
            failureCode: 'Gateway rejected customer@example.com',
        );

        $this->assertArrayNotHasKey(
            'failure_code',
            $analytics->sole()['properties'],
        );
    }

    public function test_cancelled_payment_emits_cancelled(): void
    {
        [, $payment] = $this->payment(
            initialStatus: PaymentStatus::Pending,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Cancelled,
            analytics: $analytics,
        );

        $this->assertSame(
            ProductAnalyticsEvent::PaymentCancelled,
            $analytics->sole()['event'],
        );
    }

    public function test_renewal_payment_outcomes_are_not_mixed_into_purchase_funnel(): void
    {
        [, $payment] = $this->payment(
            initialStatus: PaymentStatus::Pending,
            orderType: OrderType::Renewal,
        );

        $analytics = new RecordingPaymentAnalytics;

        $this->transition(
            payment: $payment,
            status: PaymentStatus::Paid,
            analytics: $analytics,
        );

        $this->assertSame(
            [],
            $analytics->events,
        );
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function payment(
        PaymentStatus $initialStatus,
        OrderType $orderType = OrderType::Provisioning,
    ): array {
        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->getKey(),
            'type' => $orderType,
            'cloud_provider' => CloudProviderType::ParsPack,
            'region_id' => 'de-fra',
            'size_id' => 'economy-2',
            'image_id' => 'ubuntu-24-04',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 25,
            'selected_disk_gib' => 25,
            'period' => '14d',
            'duration_hours' => 336,
            'provider_cost' => 900_000,
            'markup_percent' => 20,
            'final_amount' => 1_080_000,
            'currency' => 'IRR',
            'status' => OrderStatus::PendingPayment,
            'quote_expires_at' => now()->addHour(),
            'paid_at' => null,
        ]);

        $payment = Payment::withoutEvents(
            static fn (): Payment => Payment::query()->create([
                'order_id' => $order->getKey(),
                'gateway' => 'zarinpal',
                'amount' => $order->final_amount,
                'currency' => $order->currency,
                'status' => $initialStatus,
                'gateway_reference' => 'TEST-AUTHORITY',
                'gateway_transaction_id' => null,
                'redirect_url' => 'https://gateway.test/pay/TEST-AUTHORITY',
                'failure_code' => null,
                'verified_at' => null,
            ]),
        );

        return [
            $order,
            $payment,
        ];
    }

    private function transition(
        Payment $payment,
        PaymentStatus $status,
        RecordingPaymentAnalytics $analytics,
        ?string $failureCode = null,
    ): void {
        $payment->forceFill([
            'status' => $status,
            'failure_code' => $failureCode,
        ])->saveQuietly();

        (new PaymentAnalyticsObserver(
            $analytics,
        ))->updated(
            $payment,
        );
    }
}

final class RecordingPaymentAnalytics implements ProductAnalytics
{
    /** @var list<array{event: ProductAnalyticsEvent, user_id: int|string, properties: array<string, mixed>}> */
    public array $events = [];

    public function capture(
        ProductAnalyticsEvent $event,
        int|string $userId,
        array $properties = [],
    ): void {
        $this->events[] = [
            'event' => $event,
            'user_id' => $userId,
            'properties' => $properties,
        ];
    }

    /**
     * @return array{event: ProductAnalyticsEvent, user_id: int|string, properties: array<string, mixed>}
     */
    public function sole(): array
    {
        if (count($this->events) !== 1) {
            throw new \RuntimeException(
                'Expected exactly one analytics event.',
            );
        }

        return $this->events[0];
    }
}
