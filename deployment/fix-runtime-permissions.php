<?php

declare(strict_types=1);

if (PHP_OS_FAMILY !== 'Linux') {
    fwrite(
        STDOUT,
        "[runtime-permissions] Skipped: Linux-only runtime preparation.\n",
    );

    exit(0);
}

$root = dirname(__DIR__);
$storage = $root.'/storage';
$bootstrapCache = $root.'/bootstrap/cache';

$requiredDirectories = [
    $storage.'/logs',
    $storage.'/framework/cache',
    $storage.'/framework/sessions',
    $storage.'/framework/views',
    $bootstrapCache,
];

foreach ($requiredDirectories as $directory) {
    if (
        ! is_dir($directory)
        && ! mkdir($directory, 0775, true)
        && ! is_dir($directory)
    ) {
        fwrite(
            STDERR,
            sprintf(
                "[runtime-permissions] Unable to create directory: %s\n",
                $directory,
            ),
        );

        exit(1);
    }

    @chmod($directory, 0775);
}

$logFile = $storage.'/logs/laravel.log';

if (! file_exists($logFile)) {
    if (@touch($logFile) === false) {
        fwrite(
            STDERR,
            "[runtime-permissions] Unable to create storage/logs/laravel.log.\n",
        );

        exit(1);
    }
}

@chmod($logFile, 0664);

$isRoot = function_exists('posix_geteuid')
    && posix_geteuid() === 0;

if (! $isRoot) {
    fwrite(
        STDOUT,
        "[runtime-permissions] Writable runtime paths prepared; ownership unchanged (non-root process).\n",
    );

    exit(0);
}

$webUser = trim(
    (string) (getenv('RUNTIME_WEB_USER') ?: 'www-data'),
);
$webGroup = trim(
    (string) (getenv('RUNTIME_WEB_GROUP') ?: 'www-data'),
);

/**
 * Keep this repair intentionally limited to Laravel runtime-writable paths.
 * Provider/application data under storage is included because PHP-FPM may
 * need to create or update those files outside a deployment request.
 *
 * @return iterable<string>
 */
$runtimePaths = static function () use (
    $storage,
    $bootstrapCache,
): iterable {
    foreach ([$storage, $bootstrapCache] as $rootPath) {
        if (! file_exists($rootPath)) {
            continue;
        }

        yield $rootPath;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rootPath,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }

            yield $item->getPathname();
        }
    }
};

$ownershipFailures = 0;

foreach ($runtimePaths() as $path) {
    $isDirectory = is_dir($path);

    @chmod(
        $path,
        $isDirectory ? 0775 : 0664,
    );

    if (! @chown($path, $webUser)) {
        $ownershipFailures++;
    }

    if (! @chgrp($path, $webGroup)) {
        $ownershipFailures++;
    }
}

if ($ownershipFailures > 0) {
    fwrite(
        STDERR,
        sprintf(
            "[runtime-permissions] Warning: %d ownership operation(s) failed for %s:%s.\n",
            $ownershipFailures,
            $webUser,
            $webGroup,
        ),
    );

    /*
     * Do not make Composer itself fail here. Some Linux build containers do
     * not provide the runtime PHP-FPM account. Production can override the
     * account with RUNTIME_WEB_USER / RUNTIME_WEB_GROUP when necessary.
     */
    exit(0);
}

fwrite(
    STDOUT,
    sprintf(
        "[runtime-permissions] Runtime paths prepared for %s:%s.\n",
        $webUser,
        $webGroup,
    ),
);
