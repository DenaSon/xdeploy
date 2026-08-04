<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

use InvalidArgumentException;

enum PrivilegedExecutionMode: string
{
    case Root = 'root';

    case PasswordlessSudo = 'passwordless_sudo';

    public function wrapCommand(
        string $command,
    ): string {
        $command = trim($command);

        if ($command === '') {
            throw new InvalidArgumentException(
                'Privileged command cannot be empty.',
            );
        }

        return match ($this) {
            self::Root => $command,

            self::PasswordlessSudo => sprintf(
                'sudo -n -- bash -lc %s',
                self::quoteForPosixShell($command),
            ),
        };
    }

    private static function quoteForPosixShell(
        string $value,
    ): string {
        return "'"
            .str_replace(
                "'",
                "'\"'\"'",
                $value,
            )
            ."'";
    }
}
