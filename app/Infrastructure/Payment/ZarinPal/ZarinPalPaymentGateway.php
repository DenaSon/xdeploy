<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\ZarinPal;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentInitiationData;
use App\Domain\Billing\DTOs\PaymentInitiationRequestData;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\DTOs\PaymentVerificationRequestData;
use DateTimeImmutable;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;
use ZarinPal\Sdk\ZarinPal;

final readonly class ZarinPalPaymentGateway implements PaymentGatewayInterface
{
    private const string NAME = 'zarinpal';

    private const string REQUEST_PATH = '/pg/v4/payment/request.json';

    private const int SUCCESS_CODE = 100;

    /**
     * 100 = verified successfully
     * 101 = already verified
     */
    private const array VERIFICATION_SUCCESS_CODES = [
        100,
        101,
    ];

    public function __construct(
        private ZarinPal $sdk,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function initiate(
        PaymentInitiationRequestData $request,
    ): PaymentInitiationData {
        try {
            $response = $this->sendInitiationRequest(
                $request,
            );

            $data = $this->extractInitiationData(
                $response,
            );

            $authority = trim(
                (string) ($data['authority'] ?? ''),
            );

            if ($authority === '') {
                throw ZarinPalPaymentException::invalidResponse(
                    'initiation',
                );
            }

            return new PaymentInitiationData(
                reference: $authority,

                redirectUrl: $this->sdk
                    ->paymentGateway()
                    ->getRedirectUrl($authority),
            );
        } catch (ZarinPalPaymentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->wrapException(
                operation: 'initiation',
                exception: $exception,
            );
        }
    }

    public function verify(
        PaymentVerificationRequestData $request,
    ): PaymentVerificationData {
        try {
            $verificationRequest = new VerifyRequest;

            $verificationRequest->amount = $request->amount;
            $verificationRequest->authority = $request->reference;

            $response = $this->sdk
                ->paymentGateway()
                ->verify($verificationRequest);
        } catch (Throwable $exception) {
            throw $this->wrapException(
                operation: 'verification',
                exception: $exception,
            );
        }

        if (
            ! in_array(
                $response->code,
                self::VERIFICATION_SUCCESS_CODES,
                true,
            )
        ) {
            throw ZarinPalPaymentException::verificationFailed(
                $response->code,
            );
        }

        /*
         * ZarinPal Verify response does not reliably include Authority.
         *
         * Authority is already known from the verified request and is the
         * same gateway reference stored on our Payment.
         */
        $authority = trim(
            $request->reference,
        );

        $transactionId = trim(
            (string) $response->ref_id,
        );

        if (
            $authority === ''
            || $transactionId === ''
        ) {
            throw ZarinPalPaymentException::invalidResponse(
                'verification',
            );
        }

        return new PaymentVerificationData(
            reference: $authority,
            transactionId: $transactionId,
            amount: $request->amount,
            verifiedAt: new DateTimeImmutable,
        );
    }

    /**
     * The official SDK currently serializes nullable metadata fields
     * as null. ZarinPal rejects those fields when present with null
     * values, so initiation is sent directly through the SDK HTTP
     * client without empty metadata.
     */
    private function sendInitiationRequest(
        PaymentInitiationRequestData $request,
    ): ResponseInterface {
        $payload = [
            'merchant_id' => $this->sdk->getMerchantId(),
            'amount' => $request->amount,
            'description' => $request->description,
            'callback_url' => $request->callbackUrl,
            'currency' => $request->currency,
        ];

        return $this->sdk
            ->getHttpClient()
            ->post(
                $this->requestUrl(),
                [],
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                ),
            );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function extractInitiationData(
        ResponseInterface $response,
    ): array {
        $body = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($body)) {
            throw ZarinPalPaymentException::invalidResponse(
                'initiation',
            );
        }

        if (
            $response->getStatusCode() !== 200
            || ! empty($body['errors'])
            || empty($body['data'])
            || ! is_array($body['data'])
        ) {
            throw new ZarinPalPaymentException(
                message: sprintf(
                    'ZarinPal payment initiation failed: %s',
                    $this->extractErrorMessage($body),
                ),
                code: $this->extractErrorCode(
                    body: $body,
                    fallback: $response->getStatusCode(),
                ),
            );
        }

        $data = $body['data'];

        $code = (int) (
            $data['code']
            ?? 0
        );

        if ($code !== self::SUCCESS_CODE) {
            throw ZarinPalPaymentException::initiationFailed(
                $code,
            );
        }

        return $data;
    }

    private function requestUrl(): string
    {
        return rtrim(
            (string) $this->sdk
                ->getOptions()
                ->getBaseUrl(),
            '/',
        ).self::REQUEST_PATH;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractErrorMessage(
        array $body,
    ): string {
        $message = $body['errors']['message']
            ?? null;

        if (
            ! is_string($message)
            || trim($message) === ''
        ) {
            return 'Unknown ZarinPal error.';
        }

        return trim($message);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractErrorCode(
        array $body,
        int $fallback,
    ): int {
        $code = $body['errors']['code']
            ?? null;

        if (
            is_int($code)
            || is_numeric($code)
        ) {
            return (int) $code;
        }

        return $fallback;
    }

    private function wrapException(
        string $operation,
        Throwable $exception,
    ): ZarinPalPaymentException {
        return new ZarinPalPaymentException(
            message: sprintf(
                'ZarinPal payment %s failed: %s',
                $operation,
                $exception->getMessage(),
            ),
            code: (int) $exception->getCode(),
            previous: $exception,
        );
    }
}
