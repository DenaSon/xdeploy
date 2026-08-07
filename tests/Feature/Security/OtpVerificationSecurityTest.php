<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\TooManyOtpAttemptsException;
use App\Domain\Authentication\Services\OtpService;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Persistence\Repositories\EloquentOtpRepository;
use App\Models\Otp;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class OtpVerificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_verification_is_locked_after_five_failed_attempts(): void
    {
        $phone = PhoneNumber::from(
            '09127777777',
        );

        [
            $validCode,
            $invalidCode,
        ] = $this->distinctOtpCodes();

        $service = $this->createOtpService();

        $this->storeOtp(
            phone: $phone,
            code: $validCode,
        );

        $rateLimitKey =
            $this->verificationKey(
                $phone,
            );

        RateLimiter::clear(
            $rateLimitKey,
        );

        /*
         * First four attempts should fail normally
         * without entering lockout state.
         */
        for (
            $attempt = 1;
            $attempt <= 4;
            $attempt++
        ) {
            try {
                $service->validate(
                    phone: $phone,
                    code: $invalidCode,
                );

                self::fail(
                    sprintf(
                        'OTP attempt [%d] unexpectedly succeeded.',
                        $attempt,
                    ),
                );
            } catch (InvalidOtpException) {
                self::assertSame(
                    $attempt,
                    RateLimiter::attempts(
                        $rateLimitKey,
                    ),
                );
            }
        }

        /*
         * Fifth incorrect attempt must activate
         * the verification lockout.
         */
        try {
            $service->validate(
                phone: $phone,
                code: $invalidCode,
            );

            self::fail(
                'The fifth invalid OTP attempt did not activate the lockout.',
            );
        } catch (
            TooManyOtpAttemptsException $exception
        ) {
            self::assertGreaterThan(
                0,
                $exception->retryAfterSeconds,
            );
        }

        self::assertSame(
            5,
            RateLimiter::attempts(
                $rateLimitKey,
            ),
        );

        /*
         * Even possession of the correct OTP must not
         * bypass an active lockout.
         */
        try {
            $service->validate(
                phone: $phone,
                code: $validCode,
            );

            self::fail(
                'A correct OTP bypassed the active lockout.',
            );
        } catch (
            TooManyOtpAttemptsException $exception
        ) {
            self::assertGreaterThan(
                0,
                $exception->retryAfterSeconds,
            );
        }

        /*
         * Wrong attempts must not consume/delete
         * the stored OTP itself.
         */
        self::assertDatabaseHas(
            'otps',
            [
                'phone' => (string) $phone,
            ],
        );
    }

    public function test_successful_verification_clears_failed_attempt_counter(): void
    {
        $phone = PhoneNumber::from(
            '09128888888',
        );

        [
            $validCode,
            $invalidCode,
        ] = $this->distinctOtpCodes();

        $service = $this->createOtpService();

        $this->storeOtp(
            phone: $phone,
            code: $validCode,
        );

        $rateLimitKey =
            $this->verificationKey(
                $phone,
            );

        RateLimiter::clear(
            $rateLimitKey,
        );

        for (
            $attempt = 1;
            $attempt <= 4;
            $attempt++
        ) {
            try {
                $service->validate(
                    phone: $phone,
                    code: $invalidCode,
                );

                self::fail(
                    'Invalid OTP unexpectedly succeeded.',
                );
            } catch (InvalidOtpException) {
                //
            }
        }

        self::assertSame(
            4,
            RateLimiter::attempts(
                $rateLimitKey,
            ),
        );

        /*
         * Correct code is still allowed before the
         * fifth failed attempt.
         */
        $service->validate(
            phone: $phone,
            code: $validCode,
        );

        /*
         * Successful authentication must completely
         * clear the brute-force counter.
         */
        self::assertSame(
            0,
            RateLimiter::attempts(
                $rateLimitKey,
            ),
        );

        /*
         * OTP is single-use and must also be deleted.
         */
        self::assertDatabaseMissing(
            'otps',
            [
                'phone' => (string) $phone,
            ],
        );
    }

    /**
     * Build two valid OTP values without coupling this security test
     * to the current OTP length or generation format.
     *
     * @return array{OtpCode, OtpCode}
     */
    private function distinctOtpCodes(): array
    {
        $validCode = OtpCode::generate();

        do {
            $invalidCode = OtpCode::generate();
        } while (
            hash_equals(
                (string) $validCode,
                (string) $invalidCode,
            )
        );

        return [
            $validCode,
            $invalidCode,
        ];
    }

    private function createOtpService(): OtpService
    {
        return new OtpService(
            repository: new EloquentOtpRepository,
        );
    }

    private function storeOtp(
        PhoneNumber $phone,
        OtpCode $code,
    ): Otp {
        $repository =
            new EloquentOtpRepository;

        return $repository->store(
            phone: $phone,
            code: $code,
            expiresAt: CarbonImmutable::now()
                ->addMinutes(2),
        );
    }

    private function verificationKey(
        PhoneNumber $phone,
    ): string {
        return sprintf(
            'otp:verify:%s',
            (string) $phone,
        );
    }
}
