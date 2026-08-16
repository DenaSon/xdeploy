<?php

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
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
        'Rewrap active and pending server credential data-encryption keys using the current master key.';

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
            ->where(
                static function (Builder $query): void {
                    $query
                        ->where(
                            'credential',
                            'like',
                            ServerCredentialCipher::PREFIX.'%',
                        )
                        ->orWhere(
                            'pending_credential',
                            'like',
                            ServerCredentialCipher::PREFIX.'%',
                        );
                },
            );

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info(
                'No encrypted server credentials were found.',
            );

            return self::SUCCESS;
        }

        $this->info(
            "Found {$total} server credential record(s).",
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
                'pending_credential',
                'pending_credential_context',
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
                            $updates = [];

                            $active = $this->rewrapCredentialIfNeeded(
                                encryptedCredential: $server->getRawOriginal(
                                    'credential',
                                ),
                                credentialContext: $server->getRawOriginal(
                                    'credential_context',
                                ),
                                label: 'active credential',
                            );

                            if ($active !== null) {
                                $updates['credential'] = $active;
                            }

                            $pending = $this->rewrapCredentialIfNeeded(
                                encryptedCredential: $server->getRawOriginal(
                                    'pending_credential',
                                ),
                                credentialContext: $server->getRawOriginal(
                                    'pending_credential_context',
                                ),
                                label: 'pending credential',
                            );

                            if ($pending !== null) {
                                $updates['pending_credential'] = $pending;
                            }

                            if ($updates === []) {
                                $unchanged++;
                                $progressBar->advance();

                                continue;
                            }

                            if (! $dryRun) {
                                /*
                                 * Query builder intentionally bypasses the
                                 * Eloquent casts. Both values are already
                                 * encrypted envelopes with the same binding
                                 * context, only their wrapped data keys changed.
                                 */
                                $updates['updated_at'] = now();

                                DB::table('servers')
                                    ->where(
                                        'id',
                                        $server->getKey(),
                                    )
                                    ->update($updates);
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

    private function rewrapCredentialIfNeeded(
        mixed $encryptedCredential,
        mixed $credentialContext,
        string $label,
    ): ?string {
        if ($encryptedCredential === null) {
            return null;
        }

        if (! is_string($encryptedCredential) || $encryptedCredential === '') {
            throw new RuntimeException(
                sprintf('The stored %s is invalid.', $label),
            );
        }

        if (! $this->cipher->isEncryptedValue($encryptedCredential)) {
            return null;
        }

        if (! is_string($credentialContext) || $credentialContext === '') {
            throw new RuntimeException(
                sprintf('The %s context is missing.', $label),
            );
        }

        if (! $this->cipher->needsRewrap($encryptedCredential)) {
            return null;
        }

        $rewrappedCredential = $this->cipher->rewrap(
            encryptedValue: $encryptedCredential,
            context: $credentialContext,
        );

        /*
         * Verify the rotated envelope before writing. Plaintext exists only
         * transiently inside the cipher and is never stored, printed or logged.
         */
        $this->cipher->decrypt(
            encryptedValue: $rewrappedCredential,
            context: $credentialContext,
        );

        return $rewrappedCredential;
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
