<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Security;

final class SSHPasswordExpirationDetector
{
    private const array PATTERNS = [
        'your password has expired',
        'password has expired',
        'password expired',
        'must change your password',
        'required to change your password',
        'change your password now',
        'password change required',
    ];

    public function detects(
        string $output,
    ): bool {
        $output = $this->normalize(
            $output,
        );

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(
        string $output,
    ): string {
        $output = preg_replace(
            '/\e\[[0-9;?]*[ -\/]*[@-~]/',
            '',
            $output,
        ) ?? $output;

        return mb_strtolower(
            trim($output),
        );
    }
}
