<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Services;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\OtpExpiredException;
use App\Domain\Authentication\Exceptions\TooManyOtpAttemptsException;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

final readonly class OtpService
{
    private const int REQUEST_MAX_ATTEMPTS = 2;

    private const int REQUEST_DECAY_SECONDS = 60;

    private const int VERIFY_MAX_ATTEMPTS = 5;

    private const int VERIFY_DECAY_SECONDS = 120;

    public function __construct(
        private OtpRepositoryInterface $repository,
    ) {}

    public function generate(
        PhoneNumber $phone,
    ): OtpCode {
        $key = $this->requestRateLimitKey(
            $phone,
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

        $code = OtpCode::generate();

        $this->repository->store(
            phone: $phone,
            code: $code,
            expiresAt: CarbonImmutable::now()
                ->addMinutes(2),
        );

        return $code;
    }

    public function validate(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        $rateLimitKey = $this->verifyRateLimitKey(
            $phone,
        );

        $this->guardVerificationRateLimit(
            $rateLimitKey,
        );

        $otp = $this->repository->findByPhone(
            $phone,
        );

        if ($otp === null) {
            $this->recordFailedVerification(
                $rateLimitKey,
            );

            throw new InvalidOtpException;
        }

        if ($otp->expires_at->isPast()) {
            $this->repository->delete(
                $phone,
            );

            throw new OtpExpiredException;
        }

        if (
            ! password_verify(
                password: (string) $code,
                hash: (string) $otp->code,
            )
        ) {
            $this->recordFailedVerification(
                $rateLimitKey,
            );

            throw new InvalidOtpException;
        }

        RateLimiter::clear(
            $rateLimitKey,
        );

        $this->repository->delete(
            $phone,
        );
    }

    public function delete(
        PhoneNumber $phone,
    ): void {
        $this->repository->delete(
            $phone,
        );
    }

    public function clearExpired(): int
    {
        return $this->repository
            ->deleteExpired();
    }

    private function guardVerificationRateLimit(
        string $key,
    ): void {
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

    private function recordFailedVerification(
        string $key,
    ): void {
        RateLimiter::hit(
            $key,
            self::VERIFY_DECAY_SECONDS,
        );

        /*
         * Lock immediately on the fifth failed attempt,
         * not on the sixth request.
         */
        $this->guardVerificationRateLimit(
            $key,
        );
    }

    private function requestRateLimitKey(
        PhoneNumber $phone,
    ): string {
        return sprintf(
            'otp:request:%s',
            (string) $phone,
        );
    }

    private function verifyRateLimitKey(
        PhoneNumber $phone,
    ): string {
        return sprintf(
            'otp:verify:%s',
            (string) $phone,
        );
    }
}
