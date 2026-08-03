<?php

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RotateServerCredentialKeys extends Command
{
    protected $signature = '
        security:rotate-server-credential-keys
        {--dry-run : Validate rotation without changing database records}
        {--chunk=100 : Number of server records processed per batch}
    ';

    protected $description =
        'Rewrap server credential data-encryption keys using the current master key.';

    public function __construct(
        private readonly ServerCredentialCipher $cipher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = $this->chunkSize();

        $query = Server::query()
            ->whereNotNull('credential')
            ->where(
                'credential',
                'like',
                ServerCredentialCipher::PREFIX.'%',
            );

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info(
                'No encrypted server credentials were found.',
            );

            return self::SUCCESS;
        }

        $this->info(
            "Found {$total} encrypted server credential(s).",
        );

        if ($dryRun) {
            $this->warn(
                'Dry-run mode is enabled. No database changes will be written.',
            );
        }

        $checked = 0;
        $rotated = 0;
        $unchanged = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar(
            $total,
        );

        $progressBar->start();

        $query
            ->select([
                'id',
                'credential',
                'credential_context',
            ])
            ->chunkById(
                $chunkSize,
                function ($servers) use (
                    $dryRun,
                    &$checked,
                    &$rotated,
                    &$unchanged,
                    &$failed,
                    $progressBar,
                ): void {
                    foreach ($servers as $server) {
                        $checked++;

                        try {
                            $encryptedCredential =
                                $server->getRawOriginal(
                                    'credential',
                                );

                            $credentialContext =
                                $server->getRawOriginal(
                                    'credential_context',
                                );

                            if (
                                ! is_string($encryptedCredential)
                                || $encryptedCredential === ''
                            ) {
                                throw new RuntimeException(
                                    'The stored credential is missing or invalid.',
                                );
                            }

                            if (
                                ! is_string($credentialContext)
                                || $credentialContext === ''
                            ) {
                                throw new RuntimeException(
                                    'The credential context is missing.',
                                );
                            }

                            if (
                                ! $this->cipher->needsRewrap(
                                    $encryptedCredential,
                                )
                            ) {
                                $unchanged++;

                                $progressBar->advance();

                                continue;
                            }

                            $rewrappedCredential =
                                $this->cipher->rewrap(
                                    encryptedValue: $encryptedCredential,

                                    context: $credentialContext,
                                );

                            /*
                             * Verify the rotated envelope before writing.
                             *
                             * This decrypts the payload only for validation,
                             * but the resulting plaintext is never stored,
                             * printed or logged.
                             */
                            $this->cipher->decrypt(
                                encryptedValue: $rewrappedCredential,

                                context: $credentialContext,
                            );

                            if (! $dryRun) {
                                /*
                                 * DB query builder intentionally bypasses
                                 * the Eloquent credential cast.
                                 *
                                 * Using $server->credential here would cause
                                 * the password to be encrypted again instead
                                 * of only replacing the wrapped data key.
                                 */
                                DB::table('servers')
                                    ->where(
                                        'id',
                                        $server->getKey(),
                                    )
                                    ->update([
                                        'credential' => $rewrappedCredential,

                                        'updated_at' => now(),
                                    ]);
                            }

                            $rotated++;
                        } catch (Throwable $exception) {
                            $failed++;

                            report($exception);

                            $this->newLine(2);

                            $this->error(
                                sprintf(
                                    'Server [%s] failed: %s',
                                    (string) $server->getKey(),
                                    $exception->getMessage(),
                                ),
                            );

                            $progressBar->display();
                        }

                        $progressBar->advance();
                    }
                },
            );

        $progressBar->finish();

        $this->newLine(2);

        $this->table(
            [
                'Result',
                'Count',
            ],
            [
                [
                    'Checked',
                    $checked,
                ],
                [
                    $dryRun
                        ? 'Would rotate'
                        : 'Rotated',
                    $rotated,
                ],
                [
                    'Already current',
                    $unchanged,
                ],
                [
                    'Failed',
                    $failed,
                ],
            ],
        );

        if ($failed > 0) {
            $this->error(
                'Credential key rotation completed with failures.',
            );

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info(
                'Dry-run completed successfully.',
            );

            return self::SUCCESS;
        }

        $this->info(
            'Server credential keys were rotated successfully.',
        );

        return self::SUCCESS;
    }

    private function chunkSize(): int
    {
        $chunkSize = filter_var(
            $this->option('chunk'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 1000,
                ],
            ],
        );

        if ($chunkSize === false) {
            throw new RuntimeException(
                'The chunk option must be an integer between 1 and 1000.',
            );
        }

        return $chunkSize;
    }
}
