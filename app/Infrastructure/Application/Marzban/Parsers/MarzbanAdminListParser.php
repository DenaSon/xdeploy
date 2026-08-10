<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Parsers;

use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminInfo;
use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;

final readonly class MarzbanAdminListParser
{
    public function parse(
        string $output,
    ): MarzbanAdminOverview {
        $output = $this->stripAnsiSequences(
            $output,
        );

        $lines = preg_split(
            '/\R/u',
            $output,
        );

        if (! is_array($lines)) {
            throw MarzbanSetupInspectionException::failed();
        }

        $tableRecognized = false;
        $dataSectionStarted = false;

        /**
         * @var array<string, MarzbanAdminInfo> $admins
         */
        $admins = [];

        foreach ($lines as $line) {
            $trimmedLine = trim(
                $line,
            );

            if ($trimmedLine === '') {
                continue;
            }

            /*
             * Rich may wrap and truncate the header when no TTY is attached.
             * For example the real Marzban output can contain:
             *
             *   ┃         ┃ ... ┃
             *   ┃ Userna… ┃ ... ┃
             *   ┡━━━━━━━━━╇━━━━━┩
             *
             * Therefore the parser must not depend on the literal
             * "Username" header. The heavy header separator is a much more
             * reliable boundary between Rich's header and data rows.
             */
            if (! $dataSectionStarted) {
                if ($this->isHeaderSeparator($trimmedLine)) {
                    $tableRecognized = true;
                    $dataSectionStarted = true;

                    continue;
                }

                /*
                 * Fallback for simpler/older table renderers which do not
                 * emit Rich's dedicated header separator.
                 */
                $cells = $this->parseTableCells(
                    $line,
                );

                if (
                    $cells !== []
                    && $this->looksLikeUsernameHeader(
                        $cells[0] ?? '',
                    )
                ) {
                    $tableRecognized = true;
                    $dataSectionStarted = true;
                }

                continue;
            }

            if ($this->isBottomBorder($trimmedLine)) {
                break;
            }

            $cells = $this->parseTableCells(
                $line,
            );

            if ($cells === []) {
                continue;
            }

            $username = trim(
                $cells[0] ?? '',
            );

            /*
             * Rich wraps long values such as "Created at" over multiple
             * physical rows. Continuation rows have an empty first column,
             * so they are ignored.
             */
            if ($username === '') {
                continue;
            }

            $admins[$username] =
                new MarzbanAdminInfo(
                    username: $username,
                );
        }

        /*
         * A successful command with an unrecognizable output format must
         * never be interpreted as an empty admin list. Otherwise a future
         * Marzban CLI format change could incorrectly enable admin creation.
         */
        if (! $tableRecognized) {
            throw MarzbanSetupInspectionException::failed();
        }

        return MarzbanAdminOverview::fromAdmins(
            array_values(
                $admins,
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function parseTableCells(
        string $line,
    ): array {
        $line = strtr(
            $line,
            [
                '│' => '|',
                '┃' => '|',
                '║' => '|',
            ],
        );

        if (! str_contains($line, '|')) {
            return [];
        }

        $cells = array_map(
            static fn (
                string $cell,
            ): string => trim($cell),
            explode(
                '|',
                $line,
            ),
        );

        /*
         * Rich table rows are framed by a leading and trailing border:
         *
         *   │ admin │ ... │
         *
         * After explode() those borders create exactly one synthetic empty
         * element at each end. Remove only those framing elements.
         *
         * Do not remove every leading empty cell: wrapped continuation rows
         * intentionally have an empty first data column, for example:
         *
         *   │       │ ... │ August │
         *
         * Removing all leading empties would incorrectly promote "August"
         * (and subsequent wrapped values) into fake admin usernames.
         */
        if (
            $cells !== []
            && $cells[0] === ''
        ) {
            array_shift(
                $cells,
            );
        }

        if (
            $cells !== []
            && $cells[array_key_last($cells)] === ''
        ) {
            array_pop(
                $cells,
            );
        }

        return array_values(
            $cells,
        );
    }

    private function isHeaderSeparator(
        string $line,
    ): bool {
        return str_starts_with($line, '┡')
            || str_starts_with($line, '├')
            || str_starts_with($line, '╞');
    }

    private function isBottomBorder(
        string $line,
    ): bool {
        return str_starts_with($line, '└')
            || str_starts_with($line, '┗')
            || str_starts_with($line, '╚');
    }

    private function looksLikeUsernameHeader(
        string $value,
    ): bool {
        $value = mb_strtolower(
            trim($value),
        );

        return $value === 'username'
            || str_starts_with(
                $value,
                'userna',
            );
    }

    private function stripAnsiSequences(
        string $output,
    ): string {
        return preg_replace(
            '/\x1B\[[0-?]*[ -\/]*[@-~]/',
            '',
            $output,
        ) ?? $output;
    }
}
