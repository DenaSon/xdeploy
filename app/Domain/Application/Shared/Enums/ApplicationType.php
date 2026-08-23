<?php

namespace App\Domain\Application\Shared\Enums;

enum ApplicationType: string
{
    case Marzban = 'marzban';
    case N8n = 'n8n';
    case WordPress = 'wordpress';

    public function label(): string
    {
        return match ($this) {
            self::Marzban => 'Marzban',
            self::N8n => 'n8n',
            self::WordPress => 'WordPress',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Marzban => __('Proxy Management Platform'),
            self::N8n => __('Workflow Automation Tool'),
            self::WordPress => __('Website & Blog Platform'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Marzban => 'bg-primary/10 text-primary',
            self::N8n => 'bg-info/10 text-info',
            self::WordPress => 'bg-primary/10 text-primary',
        };
    }

    public function version(): string
    {
        return match ($this) {
            self::Marzban => '1.0',
            self::N8n => '1.0',
            self::WordPress => '6',
        };
    }

    public function requires(): array
    {
        return match ($this) {
            self::Marzban => [
                'packages' => ['curl'],
                'platforms' => ['docker'],
            ],
            self::N8n => [
                'packages' => ['curl'],
                'platforms' => ['docker'],
            ],
            self::WordPress => [
                'packages' => ['curl'],
                'platforms' => ['docker'],
            ],
        };
    }
}
