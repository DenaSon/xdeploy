<?php
declare(strict_types=1);
namespace App\Domain\Module\Enums;
enum ModuleState: string
{
    case Installed = 'installed';
    case NotInstalled = 'not_installed';
}
