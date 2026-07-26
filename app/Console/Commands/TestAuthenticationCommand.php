<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Authentication\Actions\RequestOtpAction;
use App\Application\Authentication\Actions\VerifyOtpAction;
use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Console\Command;
use Throwable;

final class TestAuthenticationCommand extends Command
{
    protected $signature = 'auth:test {phone?}';

    protected $description = 'Test the authentication flow';

    private const DEFAULT_PHONE = '09903632356';

    public function handle(
        RequestOtpAction $requestOtp,
        VerifyOtpAction $verifyOtp,
    ): int {
        $phone = PhoneNumber::from(
            $this->argument('phone') ?? self::DEFAULT_PHONE,
        );

        $this->newLine();
        $this->info('=== Authentication Test ===');
        $this->line('Phone: '.$phone);

        try {
            /*
             |--------------------------------------------------------------------------
             | Request OTP
             |--------------------------------------------------------------------------
             */

            $this->info('Generating and sending OTP...');

            $requestOtp->handle(
                new RequestOtpData(
                    phone: $phone,
                ),
            );

            $this->info('✓ OTP generated and SMS sent.');

            /*
             |--------------------------------------------------------------------------
             | Verify OTP
             |--------------------------------------------------------------------------
             */

            $code = $this->ask('Enter received OTP');

            $token = $verifyOtp->handle(
                new VerifyOtpData(
                    phone: $phone,
                    code: OtpCode::from($code),
                ),
            );

            $this->info('✓ Authentication successful.');
            $this->newLine();

            $this->table(
                ['Key', 'Value'],
                [
                    ['Phone', (string) $phone],
                    ['Token', $token->plainTextToken],
                ],
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();

            $this->error('Authentication failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
