<?php

declare(strict_types=1);

namespace Tests\Support\SSH;

/**
 * Test-only socket seam for SSHPortReadinessProbe.
 *
 * SSHPortReadinessProbe calls fsockopen() unqualified from this namespace.
 * PHP resolves a namespaced function before falling back to the global
 * function, allowing Cloud feature tests to avoid real network access
 * without modifying production code.
 */
final class FakeSshPortSocket
{
    private static bool $ready = true;

    public static function ready(): void
    {
        self::$ready = true;
    }

    public static function unavailable(): void
    {
        self::$ready = false;
    }

    public static function reset(): void
    {
        self::$ready = true;
    }

    public static function isReady(): bool
    {
        return self::$ready;
    }
}

/**
 * Test-only replacement for the global fsockopen() used by
 * SSHPortReadinessProbe.
 *
 * @param  mixed  $errorCode
 * @param  mixed  $errorMessage
 * @return resource|false
 */
function fsockopen(
    string $hostname,
    int $port = -1,
    &$errorCode = null,
    &$errorMessage = null,
    ?float $timeout = null,
) {
    if (! FakeSshPortSocket::isReady()) {
        $errorCode = 111;
        $errorMessage = 'Connection refused by test socket fake.';

        return false;
    }

    return fopen(
        'php://temp',
        'r+',
    );
}
