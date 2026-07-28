<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Services;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\OtpExpiredException;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

final readonly class OtpService
{
    public function __construct(
        private OtpRepositoryInterface $repository,
    ) {}

    public function generate(
        PhoneNumber $phone,
    ): OtpCode {

        $key = 'otp:'.$phone;

        if (RateLimiter::tooManyAttempts($key, 2)) {
            throw new TooManyOtpRequestsException;
        }

        RateLimiter::hit($key, 60);

        $code = OtpCode::generate();

        $this->repository->store(
            phone: $phone,
            code: $code,
            expiresAt: CarbonImmutable::now()->addMinutes(2),
        );

        return $code;
    }

    public function validate(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        $otp = $this->repository->findByPhone($phone);

        if (! $otp) {
            throw new InvalidOtpException;
        }

        if ($otp->expires_at->isPast()) {
            $this->repository->delete($phone);

            throw new OtpExpiredException;
        }

        if ((string) $code !== $otp->code) {
            throw new InvalidOtpException;
        }

        $this->repository->delete($phone);
    }

    public function delete(
        PhoneNumber $phone,
    ): void {
        $this->repository->delete($phone);
    }

    public function clearExpired(): int
    {
        return $this->repository->deleteExpired();
    }
}
