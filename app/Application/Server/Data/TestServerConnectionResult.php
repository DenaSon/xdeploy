<?php

declare(strict_types=1);

namespace App\Application\Server\Data;

use App\Application\Server\Enums\ServerConnectionTestStatus;
use App\Domain\Server\DTOs\OperatingSystemInfo;

final readonly class TestServerConnectionResult
{
    public function __construct(
        public ServerConnectionTestStatus $status,
        public ?OperatingSystemInfo $operatingSystem = null,
    ) {}

    public static function insufficientPrivileges(
        OperatingSystemInfo $operatingSystem,
    ): self {
        return new self(
            status: ServerConnectionTestStatus::InsufficientPrivileges,
            operatingSystem: $operatingSystem,
        );
    }

    public static function ready(
        OperatingSystemInfo $operatingSystem,
    ): self {
        return new self(
            status: ServerConnectionTestStatus::Ready,
            operatingSystem: $operatingSystem,
        );
    }

    public static function connectionFailed(): self
    {
        return new self(
            ServerConnectionTestStatus::ConnectionFailed,
        );
    }

    public static function passwordChangeRequired(): self
    {
        return new self(
            ServerConnectionTestStatus::PasswordChangeRequired,
        );
    }

    public static function commandUnavailable(): self
    {
        return new self(
            ServerConnectionTestStatus::CommandUnavailable,
        );
    }

    public static function unsupportedOperatingSystem(
        OperatingSystemInfo $operatingSystem,
    ): self {
        return new self(
            status: ServerConnectionTestStatus::UnsupportedOperatingSystem,
            operatingSystem: $operatingSystem,
        );
    }

    public function isReady(): bool
    {
        return $this->status
            === ServerConnectionTestStatus::Ready;
    }
}
