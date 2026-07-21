<?php
declare(strict_types=1);
namespace App\Domain\Module\Enums;
enum ModuleState: string
{
    case NotInstalled = 'not_installed';
    case Installed = 'installed';
    case Running = 'running';
}
