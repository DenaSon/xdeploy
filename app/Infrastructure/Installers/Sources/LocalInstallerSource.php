<?php

declare(strict_types=1);

namespace App\Infrastructure\Installers\Sources;

use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use RuntimeException;

final readonly class LocalInstallerSource implements InstallerSourceInterface
{
    public function __construct(
        private string $rootPath,
    ) {}

    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        $expectedSha256 = $this->validatedSha256(
            $expectedSha256,
        );

        $absolutePath = $this->absolutePath(
            $relativePath,
        );

        $contents = file_get_contents(
            $absolutePath,
        );

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read the local installer asset.',
            );
        }

        $actualSha256 = hash(
            'sha256',
            $contents,
        );

        if (
            ! hash_equals(
                $expectedSha256,
                $actualSha256,
            )
        ) {
            throw new RuntimeException(
                'Local installer checksum does not match the pinned checksum.',
            );
        }

        return sprintf(
            <<<'BASH'
set -Eeuo pipefail

installer="$(mktemp /tmp/xdeploy-installer.XXXXXX)"

cleanup() {
    rm -f "$installer"
}

trap cleanup EXIT

printf '%%s' %s \
    | base64 --decode \
    > "$installer"

printf '%%s  %%s\n' %s "$installer" \
    | sha256sum --check --strict -

chmod 0700 "$installer"

bash "$installer"
BASH,
            $this->quoteForPosixShell(
                base64_encode(
                    $contents,
                ),
            ),
            $this->quoteForPosixShell(
                $expectedSha256,
            ),
        );
    }

    private function absolutePath(
        string $relativePath,
    ): string {
        $relativePath = $this->validatedRelativePath(
            $relativePath,
        );

        $rootPath = rtrim(
            $this->rootPath,
            DIRECTORY_SEPARATOR,
        );

        if ($rootPath === '') {
            throw new RuntimeException(
                'Local installer root path is not configured.',
            );
        }

        $absolutePath = $rootPath
            .DIRECTORY_SEPARATOR
            .str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath,
            );

        if (! is_file($absolutePath)) {
            throw new RuntimeException(
                sprintf(
                    'Local installer asset [%s] does not exist.',
                    $relativePath,
                ),
            );
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException(
                sprintf(
                    'Local installer asset [%s] is not readable.',
                    $relativePath,
                ),
            );
        }

        return $absolutePath;
    }

    private function validatedRelativePath(
        string $relativePath,
    ): string {
        $relativePath = trim(
            str_replace(
                '\\',
                '/',
                $relativePath,
            ),
            '/',
        );

        if (
            $relativePath === ''
            || str_contains(
                $relativePath,
                '..',
            )
            || preg_match(
                '/^[A-Za-z0-9._\/-]+$/',
                $relativePath,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Installer relative path is invalid.',
            );
        }

        return $relativePath;
    }

    private function validatedSha256(
        string $sha256,
    ): string {
        $sha256 = strtolower(
            trim(
                $sha256,
            ),
        );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $sha256,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Installer SHA-256 is invalid.',
            );
        }

        return $sha256;
    }

    private function quoteForPosixShell(
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
