<?php

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use Illuminate\Console\Command;
use Throwable;

final class MigrateServerCredentials extends Command
{
    protected $signature = '
        security:migrate-server-credentials
        {--dry-run : Validate credentials without writing changes}
    ';

    protected $description =
        'Migrate legacy Laravel encrypted server credentials to the versioned xDeploy credential envelope.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option(
            'dry-run',
        );

        $migrated = 0;
        $failed = 0;

        $query = Server::query()
            ->whereNotNull('credential')
            ->where(
                'credential',
                'not like',
                ServerCredentialCipher::PREFIX.'%',
            );

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info(
                'No legacy server credentials were found.',
            );

            return self::SUCCESS;
        }

        $this->info(
            "Found {$total} legacy server credential(s).",
        );

        if ($dryRun) {
            $this->warn(
                'Dry-run mode: no database changes will be written.',
            );
        }

        $query
            ->select([
                'id',
                'credential',
                'credential_context',
            ])
            ->chunkById(
                100,
                function ($servers) use (
                    $dryRun,
                    &$migrated,
                    &$failed,
                ): void {
                    foreach ($servers as $server) {
                        try {
                            /*
                             * The custom cast decrypts the legacy
                             * Laravel encrypted value.
                             */
                            $plaintext = $server->credential;

                            if (! is_string($plaintext)) {
                                throw new \RuntimeException(
                                    'Credential did not decrypt to a string.',
                                );
                            }

                            if (! $dryRun) {
                                /*
                                 * Setting the plaintext invokes the new
                                 * cast and stores a versioned envelope.
                                 */
                                $server->credential = $plaintext;

                                $server->saveQuietly();
                            }

                            $migrated++;

                            $this->line(
                                "Server [{$server->getKey()}] validated."
                            );
                        } catch (Throwable $exception) {
                            $failed++;

                            report($exception);

                            $this->error(
                                "Server [{$server->getKey()}] failed: {$exception->getMessage()}",
                            );
                        }
                    }
                },
            );

        $this->newLine();

        $this->info(
            "Successful: {$migrated}",
        );

        if ($failed > 0) {
            $this->error(
                "Failed: {$failed}",
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
