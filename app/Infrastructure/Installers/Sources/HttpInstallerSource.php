<?php

declare(strict_types=1);

namespace App\Infrastructure\Installers\Sources;

use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use RuntimeException;

final readonly class HttpInstallerSource implements InstallerSourceInterface
{
    public function __construct(
        private string $baseUrl,
    ) {}

    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        $relativePath = $this->validatedRelativePath(
            $relativePath,
        );

        $expectedSha256 = $this->validatedSha256(
            $expectedSha256,
        );

        $url = sprintf(
            '%s/%s',
            rtrim(
                $this->baseUrl,
                '/',
            ),
            $relativePath,
        );

        if (
            filter_var(
                $url,
                FILTER_VALIDATE_URL,
            ) === false
            || strtolower(
                (string) parse_url(
                    $url,
                    PHP_URL_SCHEME,
                ),
            ) !== 'https'
        ) {
            throw new RuntimeException(
                'Remote installer URL must be a valid HTTPS URL.',
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

curl \
    --proto '=https' \
    --tlsv1.2 \
    --fail \
    --silent \
    --show-error \
    --location \
    --retry 3 \
    --retry-all-errors \
    --connect-timeout 10 \
    --max-time 90 \
    %s \
    -o "$installer"

printf '%%s  %%s\n' %s "$installer" \
    | sha256sum --check --strict -

chmod 0700 "$installer"

bash "$installer"
BASH,
            $this->quoteForPosixShell(
                $url,
            ),
            $this->quoteForPosixShell(
                $expectedSha256,
            ),
        );
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
