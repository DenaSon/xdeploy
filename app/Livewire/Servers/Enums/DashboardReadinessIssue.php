<?php

declare(strict_types=1);

namespace App\Livewire\Servers\Enums;

enum DashboardReadinessIssue: string
{
    case PasswordChangeRequired = 'password_change_required';

    case CommandUnavailable = 'command_unavailable';

    case UnsupportedOperatingSystem = 'unsupported_operating_system';

    case OperatingSystemInspectionFailed = 'operating_system_inspection_failed';
}
