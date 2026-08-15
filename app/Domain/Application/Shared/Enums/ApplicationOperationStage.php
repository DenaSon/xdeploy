<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\Enums;

enum ApplicationOperationStage: string
{
    case Queued = 'queued';
    case Connecting = 'connecting';
    case CheckingServer = 'checking_server';
    case PreparingServer = 'preparing_server';
    case InstallingDependencies = 'installing_dependencies';
    case PreparingPlatform = 'preparing_platform';
    case InstallingApplication = 'installing_application';
    case StartingApplication = 'starting_application';
    case Completed = 'completed';
}
