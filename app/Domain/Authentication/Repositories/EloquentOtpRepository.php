<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Repositories;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\Otp;
use Carbon\CarbonInterface;

final readonly class EloquentOtpRepository implements OtpRepositoryInterface
{
    public function store(
        PhoneNumber $phone,
        OtpCode $code,
        CarbonInterface $expiresAt,
    ): Otp {
        Otp::query()
            ->where('phone', (string) $phone)
            ->delete();

        return Otp::create([
            'phone' => (string) $phone,
            'code' => (string) $code,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findByPhone(
        PhoneNumber $phone,
    ): ?Otp {
        return Otp::query()
            ->where('phone', (string) $phone)
            ->first();
    }

    public function delete(
        PhoneNumber $phone,
    ): void {
        Otp::query()
            ->where('phone', (string) $phone)
            ->delete();
    }

    public function deleteExpired(): int
    {
        return Otp::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}
