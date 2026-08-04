<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudServerStatus;
use DateTimeImmutable;
use SensitiveParameter;

final readonly class CreatedCloudServerData
{
    private ?string $generatedPassword;

    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public CloudServerStatus $status,
        public ?string $username,
        public ?DateTimeImmutable $createdAt,
        #[SensitiveParameter]
        ?string $generatedPassword,
    ) {
        $this->generatedPassword = $generatedPassword;
    }

    public function generatedPassword(): ?string
    {
        return $this->generatedPassword;
    }

    public function hasGeneratedPassword(): bool
    {
        return is_string($this->generatedPassword)
            && $this->generatedPassword !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region_id' => $this->regionId,
            'status' => $this->status->value,
            'username' => $this->username,
            'created_at' => $this->createdAt?->format(
                DATE_ATOM,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            ...$this->toArray(),
            'generated_password' => $this->hasGeneratedPassword()
                ? '[REDACTED]'
                : null,
        ];
    }
}
