<?php

declare(strict_types=1);

namespace App\Application\Module\DTOs;

use App\Domain\Module\Enums\ModuleType;
use Livewire\Wireable;

final readonly class ModuleListItem implements Wireable
{
    public function __construct(
        public ModuleType $type,
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
            type: ModuleType::from($value['type']),
            name: $value['name'],
        );
    }
}
