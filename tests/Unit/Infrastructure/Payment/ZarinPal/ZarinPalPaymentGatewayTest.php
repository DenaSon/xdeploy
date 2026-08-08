<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Payment\ZarinPal;

use App\Domain\Billing\DTOs\PaymentInitiationRequestData;
use App\Domain\Billing\DTOs\PaymentVerificationRequestData;
use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentException;
use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentGateway;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

final class ZarinPalPaymentGatewayTest extends TestCase
{
    private const string MERCHANT_ID =
        '11111111-1111-1111-1111-111111111111';

    private const string SANDBOX_URL =
        'https://sandbox.zarinpal.com';

    public function test_it_initiates_payment_without_empty_metadata(): void
    {
        $authority = $this->authority();

        $http = Mockery::mock(
            HttpMethodsClientInterface::class,
        );

        $http
            ->shouldReceive('post')
            ->once()
            ->with(
                self::SANDBOX_URL.'/pg/v4/payment/request.json',
                [],
                Mockery::on(
                    function (mixed $body): bool {
                        if (! is_string($body)) {
                            return false;
                        }

                        $payload = json_decode(
                            $body,
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );

                        $this->assertSame(
                            self::MERCHANT_ID,
                            $payload['merchant_id'],
                        );

                        $this->assertSame(
                            1_781_760,
                            $payload['amount'],
                        );

                        $this->assertSame(
                            'xDeploy order #1',
                            $payload['description'],
                        );

                        $this->assertSame(
                            'http://localhost/payments/zarinpal/callback',
                            $payload['callback_url'],
                        );

                        $this->assertSame(
                            'IRR',
                            $payload['currency'],
                        );

                        /*
                         * Regression:
                         *
                         * ZarinPal rejected the SDK-generated payload
                         * because metadata.mobile was sent as null.
                         *
                         * Our adapter must not send empty metadata.
                         */
                        $this->assertArrayNotHasKey(
                            'metadata',
                            $payload,
                        );

                        return true;
                    },
                ),
            )
            ->andReturn(
                $this->response([
                    'data' => [
                        'authority' => $authority,
                        'code' => 100,
                        'message' => 'Success',
                        'fee_type' => 'Merchant',
                        'fee' => 0,
                        'amount' => 1_781_760,
                    ],
                    'errors' => [],
                ]),
            );

        $gateway = $this->gateway(
            $http,
        );

        $result = $gateway->initiate(
            new PaymentInitiationRequestData(
                orderId: 1,
                amount: 1_781_760,
                currency: 'IRR',
                callbackUrl: 'http://localhost/payments/zarinpal/callback',
                description: 'xDeploy order #1',
            ),
        );

        $this->assertSame(
            $authority,
            $result->reference,
        );

        $this->assertSame(
            self::SANDBOX_URL
            .'/pg/StartPay/'
            .$authority,
            $result->redirectUrl,
        );
    }

    public function test_it_preserves_initiation_error_code_and_message(): void
    {
        $http = Mockery::mock(
            HttpMethodsClientInterface::class,
        );

        $http
            ->shouldReceive('post')
            ->once()
            ->andReturn(
                $this->response(
                    body: [
                        'data' => [],
                        'errors' => [
                            'code' => -9,
                            'message' => 'The metadata.mobile must be a string.',
                        ],
                    ],
                    status: 422,
                ),
            );

        $gateway = $this->gateway(
            $http,
        );

        try {
            $gateway->initiate(
                new PaymentInitiationRequestData(
                    orderId: 1,
                    amount: 1_781_760,
                    currency: 'IRR',
                    callbackUrl: 'http://localhost/payments/zarinpal/callback',
                    description: 'xDeploy order #1',
                ),
            );

            $this->fail(
                'Expected ZarinPalPaymentException was not thrown.',
            );
        } catch (ZarinPalPaymentException $exception) {
            $this->assertSame(
                -9,
                $exception->getCode(),
            );

            $this->assertStringContainsString(
                'metadata.mobile',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_verifies_payment_using_request_authority(): void
    {
        $authority = $this->authority();

        $http = Mockery::mock(
            HttpMethodsClientInterface::class,
        );

        $http
            ->shouldReceive('post')
            ->once()
            ->with(
                self::SANDBOX_URL.'/pg/v4/payment/verify.json',
                [],
                Mockery::on(
                    function (mixed $body) use (
                        $authority,
                    ): bool {
                        if (! is_string($body)) {
                            return false;
                        }

                        $payload = json_decode(
                            $body,
                            true,
                            512,
                            JSON_THROW_ON_ERROR,
                        );

                        $this->assertSame(
                            self::MERCHANT_ID,
                            $payload['merchant_id'],
                        );

                        $this->assertSame(
                            1_781_760,
                            $payload['amount'],
                        );

                        $this->assertSame(
                            $authority,
                            $payload['authority'],
                        );

                        return true;
                    },
                ),
            )
            ->andReturn(
                $this->response([
                    'data' => [
                        /*
                         * Deliberately no "authority".
                         *
                         * Real ZarinPal Verify response did not contain
                         * authority and the SDK's typed property remained
                         * uninitialized.
                         */
                        'code' => 100,
                        'message' => 'Verified',
                        'ref_id' => '404061201',
                        'card_pan' => '123456******1234',
                        'card_hash' => 'test-card-hash',
                        'fee_type' => 'Merchant',
                        'fee' => '0',
                    ],
                    'errors' => [],
                ]),
            );

        $gateway = $this->gateway(
            $http,
        );

        $result = $gateway->verify(
            new PaymentVerificationRequestData(
                reference: $authority,
                amount: 1_781_760,
                currency: 'IRR',
            ),
        );

        /*
         * Regression:
         *
         * This proves we use the Authority already stored in Payment
         * instead of accessing VerifyResponse::$authority.
         */
        $this->assertSame(
            $authority,
            $result->reference,
        );

        $this->assertSame(
            '404061201',
            $result->transactionId,
        );

        $this->assertSame(
            1_781_760,
            $result->amount,
        );
    }

    public function test_it_accepts_already_verified_code_101(): void
    {
        $authority = $this->authority();

        $http = Mockery::mock(
            HttpMethodsClientInterface::class,
        );

        $http
            ->shouldReceive('post')
            ->once()
            ->andReturn(
                $this->response([
                    'data' => [
                        'code' => 101,
                        'message' => 'Already verified',
                        'ref_id' => '404061201',
                        'card_pan' => '123456******1234',
                        'card_hash' => 'test-card-hash',
                        'fee_type' => 'Merchant',
                        'fee' => '0',
                    ],
                    'errors' => [],
                ]),
            );

        $gateway = $this->gateway(
            $http,
        );

        $result = $gateway->verify(
            new PaymentVerificationRequestData(
                reference: $authority,
                amount: 1_781_760,
                currency: 'IRR',
            ),
        );

        $this->assertSame(
            $authority,
            $result->reference,
        );

        $this->assertSame(
            '404061201',
            $result->transactionId,
        );

        $this->assertSame(
            1_781_760,
            $result->amount,
        );
    }

    public function test_it_rejects_unsuccessful_verification(): void
    {
        $authority = $this->authority();

        $http = Mockery::mock(
            HttpMethodsClientInterface::class,
        );

        $http
            ->shouldReceive('post')
            ->once()
            ->with(
                self::SANDBOX_URL.'/pg/v4/payment/verify.json',
                [],
                Mockery::type('string'),
            )
            ->andReturn(
                $this->response([
                    'data' => [
                        'code' => -51,
                        'message' => 'Payment was not successful.',
                    ],
                    'errors' => [],
                ]),
            );

        $gateway = $this->gateway(
            $http,
        );

        try {
            $gateway->verify(
                new PaymentVerificationRequestData(
                    reference: $authority,
                    amount: 1_781_760,
                    currency: 'IRR',
                ),
            );

            $this->fail(
                'Expected ZarinPalPaymentException was not thrown.',
            );
        } catch (ZarinPalPaymentException $exception) {
            $this->assertSame(
                -51,
                $exception->getCode(),
            );

            $this->assertStringContainsString(
                'verification failed',
                strtolower(
                    $exception->getMessage(),
                ),
            );
        }
    }

    private function gateway(
        HttpMethodsClientInterface $http,
    ): ZarinPalPaymentGateway {
        $sdk = new ZarinPal(
            new Options([
                'merchant_id' => self::MERCHANT_ID,
                'sandbox' => true,
                'sandbox_base_url' => self::SANDBOX_URL,
            ]),
        );

        $sdk->setHttpClient(
            $http,
        );

        return new ZarinPalPaymentGateway(
            sdk: $sdk,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function response(
        array $body,
        int $status = 200,
    ): ResponseInterface {
        $responseFactory =
            Psr17FactoryDiscovery::findResponseFactory();

        $streamFactory =
            Psr17FactoryDiscovery::findStreamFactory();

        $stream = $streamFactory->createStream(
            json_encode(
                $body,
                JSON_THROW_ON_ERROR,
            ),
        );

        return $responseFactory
            ->createResponse($status)
            ->withHeader(
                'Content-Type',
                'application/json',
            )
            ->withBody(
                $stream,
            );
    }

    private function authority(): string
    {
        /*
         * Valid ZarinPal Authority format:
         *
         * A/S + 35 alphanumeric characters.
         */
        return 'A'.str_repeat(
            '1',
            35,
        );
    }
}
