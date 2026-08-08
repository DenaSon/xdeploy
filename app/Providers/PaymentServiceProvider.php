<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentGateway;
use Illuminate\Support\ServiceProvider;
use LogicException;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PaymentGatewayInterface::class,
            function (): PaymentGatewayInterface {
                $default = (string) config(
                    'payments.default',
                    '',
                );

                if ($default !== 'zarinpal') {
                    throw new LogicException(
                        "Unsupported payment gateway [{$default}].",
                    );
                }

                $merchantId = trim((string) config(
                    'payments.gateways.zarinpal.merchant_id',
                    '',
                ));

                if ($merchantId === '') {
                    throw new LogicException(
                        'ZarinPal merchant ID is not configured.',
                    );
                }

                $options = new Options([
                    'merchant_id' => $merchantId,
                    'access_token' => (string) config(
                        'payments.gateways.zarinpal.access_token',
                        '',
                    ),
                    'sandbox' => (bool) config(
                        'payments.gateways.zarinpal.sandbox',
                        false,
                    ),
                ]);

                return new ZarinPalPaymentGateway(
                    sdk: new ZarinPal($options),
                );
            },
        );
    }
}
