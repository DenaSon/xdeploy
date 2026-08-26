<?php

declare(strict_types=1);

namespace App\Application\Notifications;

enum NotificationTopic: string
{
    case Servers = 'servers';
    case Support = 'support';
    case Account = 'account';
    case Billing = 'billing';
}
