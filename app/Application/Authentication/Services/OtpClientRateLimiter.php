<?php

declare(strict_types=1);

namespace App\Application\Authentication\Services;

use App\Domain\Authentication\Exceptions\TooManyOtpAttemptsException;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use Illuminate\Support\Facades\RateLimiter;

final readonly class OtpClientRateLimiter
{
    private const int REQUEST_MAX_ATTEMPTS = 5;

    private const int REQUEST_DECAY_SECONDS = 60;

    private const int VERIFY_MAX_ATTEMPTS = 20;

    private const int VERIFY_DECAY_SECONDS = 120;

    public function hitRequest(
        string $clientIdentifier,
    ): void {
        $key = $this->requestKey(
            $clientIdentifier,
        );

        if (
            RateLimiter::tooManyAttempts(
                $key,
                self::REQUEST_MAX_ATTEMPTS,
            )
        ) {
            throw new TooManyOtpRequestsException;
        }

        RateLimiter::hit(
            $key,
            self::REQUEST_DECAY_SECONDS,
        );
    }

    public function guardVerification(
        string $clientIdentifier,
    ): void {
        $key = $this->verifyKey(
            $clientIdentifier,
        );

        if (
            ! RateLimiter::tooManyAttempts(
                $key,
                self::VERIFY_MAX_ATTEMPTS,
            )
        ) {
            return;
        }

        throw new TooManyOtpAttemptsException(
            retryAfterSeconds: RateLimiter::availableIn(
                $key,
            ),
        );
    }

    public function recordFailedVerification(
        string $clientIdentifier,
    ): void {
        $key = $this->verifyKey(
            $clientIdentifier,
        );

        RateLimiter::hit(
            $key,
            self::VERIFY_DECAY_SECONDS,
        );

        $this->guardVerification(
            $clientIdentifier,
        );
    }

    private function requestKey(
        string $clientIdentifier,
    ): string {
        return sprintf(
            'otp:client:request:%s',
            $this->hashIdentifier(
                $clientIdentifier,
            ),
        );
    }

    private function verifyKey(
        string $clientIdentifier,
    ): string {
        return sprintf(
            'otp:client:verify:%s',
            $this->hashIdentifier(
                $clientIdentifier,
            ),
        );
    }

    private function hashIdentifier(
        string $clientIdentifier,
    ): string {
        return hash(
            'sha256',
            trim($clientIdentifier),
        );
    }
}
