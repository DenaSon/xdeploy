<?php

declare(strict_types=1);

namespace App\Application\Cloud\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchExpiredCloudServerTerminationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        /*
         * Reuse the existing Cloud side-effect worker used for
         * provisioning. No new worker process is required for MVP.
         */
        $this->onQueue(
            'provisioning',
        );
    }

    public function uniqueId(): string
    {
        return 'cloud-server-expiration-scan';
    }

    public function handle(): void
    {
        Server::query()
            ->expiredCloud()
            ->select([
                'id',
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                static function ($servers): void {
                    foreach ($servers as $server) {
                        TerminateExpiredCloudServerJob::dispatch(
                            (int) $server->getKey(),
                        );
                    }
                },
            );
    }
}
