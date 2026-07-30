<?php

declare(strict_types=1);

namespace App\Application\Applications\DTOs;

use App\Domain\Application\Shared\Enums\ApplicationType;
use Livewire\Wireable;

final readonly class ApplicationListItem implements Wireable
{
    public function __construct(
        public ApplicationType $type,
        public string $name,
    ) {}

    public function toLivewire(): array
    {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
        ];
    }

    public static function fromLivewire($value): self
    {
        return new self(
            type: ApplicationType::from($value['type']),
            name: $value['name'],
        );
    }
}
