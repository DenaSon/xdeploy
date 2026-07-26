<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Contracts;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\Otp;
use Carbon\CarbonInterface;

interface OtpRepositoryInterface
{
    public function store(
        PhoneNumber $phone,
        OtpCode $code,
        CarbonInterface $expiresAt,
    ): Otp;

    public function findByPhone(
        PhoneNumber $phone,
    ): ?Otp;

    public function delete(
        PhoneNumber $phone,
    ): void;

    public function deleteExpired(): int;
}
