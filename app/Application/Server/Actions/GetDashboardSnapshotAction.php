<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Application\Server\Data\DashboardSnapshotData;
use App\Application\Server\ServerReadExecutor;
use App\Domain\Server\DTOs\SystemServiceData;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Infrastructure\Linux\Exceptions\OperatingSystemInspectionException;
use App\Infrastructure\SSH\Exceptions\SSHCommandUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordChangeRequiredException;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class GetDashboardSnapshotAction
{
    private const string CACHE_PREFIX =
        'dashboard:v2:server';

    /**
     * Static server facts can stay cached for several minutes.
     * Runtime data remains intentionally short-lived.
     *
     * @var array<string, int>
     */
    private const array CACHE_TTLS = [
        'identity' => 300,
        'cpu' => 600,
        'resources' => 25,
        'services' => 60,
        'docker' => 30,
    ];

    /**
     * @var list<string>
     */
    private const array SEGMENTS = [
        'identity',
        'cpu',
        'resources',
        'services',
        'docker',
    ];

    public function __construct(
        private ServerReadExecutor $executor,
        private GetServerIdentityAction $identity,
        private GetCpuInformationAction $cpu,
        private GetResourceUsageAction $resources,
        private GetSystemServicesAction $services,
        private GetDockerRuntimeAction $docker,
    ) {}

    public function cached(
        Server $server,
    ): DashboardSnapshotData {
        $values = [
            'identity' => [],
            'cpu' => [],
            'resources' => [],
            'services' => [],
            'docker' => [],
        ];

        $loadedSegments = [];

        foreach (self::SEGMENTS as $segment) {
            $key = $this->cacheKey(
                server: $server,
                segment: $segment,
            );

            if (! Cache::has($key)) {
                continue;
            }

            $cached = Cache::get($key);

            if (! is_array($cached)) {
                Cache::forget($key);

                continue;
            }

            $values[$segment] = $cached;
            $loadedSegments[] = $segment;
        }

        return new DashboardSnapshotData(
            identity: $values['identity'],
            cpu: $values['cpu'],
            resources: $values['resources'],
            services: array_values(
                $values['services'],
            ),
            docker: $values['docker'],
            loadedSegments: $loadedSegments,
        );
    }

    public function handle(
        Server $server,
        bool $fresh = false,
    ): DashboardSnapshotData {
        /*
         * Never delete the last successful snapshot before a refresh.
         *
         * When $fresh is true we force every segment to be read again,
         * but cached values stay intact until a successful replacement
         * is written. If SSH fails, the previous snapshot remains
         * available for the next page load.
         */
        $cached = $this->cached(
            $server,
        );

        $missingSegments = $fresh
            ? self::SEGMENTS
            : array_values(
                array_diff(
                    self::SEGMENTS,
                    $cached->loadedSegments,
                ),
            );

        /*
         * A warm Dashboard can render without opening SSH at all.
         */
        if ($missingSegments === []) {
            return $cached;
        }

        /*
         * All cache misses (or all segments during a forced refresh)
         * are filled inside ONE ServerReadExecutor session, so connection
         * + readiness checks happen only once.
         */
        return $this->executor->execute(
            $server,
            function () use (
                $server,
                $cached,
                $missingSegments,
            ): DashboardSnapshotData {
                $values = [
                    'identity' => $cached->identity,
                    'cpu' => $cached->cpu,
                    'resources' => $cached->resources,
                    'services' => $cached->services,
                    'docker' => $cached->docker,
                ];

                $loadedSegments =
                    $cached->loadedSegments;

                $errors = [];

                foreach (
                    $missingSegments as $segment
                ) {
                    $value = $this->readSegment(
                        $segment,
                        $errors,
                    );

                    if ($value === null) {
                        continue;
                    }

                    $values[$segment] = $value;
                    $loadedSegments[] = $segment;

                    Cache::put(
                        $this->cacheKey(
                            server: $server,
                            segment: $segment,
                        ),
                        $value,
                        now()->addSeconds(
                            self::CACHE_TTLS[$segment],
                        ),
                    );
                }

                return new DashboardSnapshotData(
                    identity: $values['identity'],
                    cpu: $values['cpu'],
                    resources: $values['resources'],
                    services: array_values(
                        $values['services'],
                    ),
                    docker: $values['docker'],
                    loadedSegments: array_values(
                        array_unique(
                            $loadedSegments,
                        ),
                    ),
                    errors: $errors,
                );
            },
        );
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function readSegment(
        string $segment,
        array &$errors,
    ): ?array {
        try {
            return match ($segment) {
                'identity' => $this->identity
                    ->handle()
                    ->toArray(),

                'cpu' => $this->cpu
                    ->handle()
                    ->toArray(),

                'resources' => $this->resources
                    ->handle()
                    ->toArray(),

                'services' => array_map(
                    static fn (
                        SystemServiceData $service,
                    ): array => $service->toArray(),
                    $this->services->handle(),
                ),

                'docker' => $this->docker
                    ->handle()
                    ->toArray(),

                default => [],
            };
        } catch (
            SSHPasswordChangeRequiredException
            |SSHCommandUnavailableException
            |UnsupportedOperatingSystemException
            |OperatingSystemInspectionException
            |SSHConnectionException $exception
        ) {
            /*
             * These errors affect the SSH/session capability itself.
             * Let Dashboard present them once at page level.
             */
            throw $exception;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $errors[$segment] =
                $this->sectionErrorMessage(
                    $segment,
                );

            return null;
        }
    }

    private function sectionErrorMessage(
        string $segment,
    ): string {
        return match ($segment) {
            'identity' => 'دریافت مشخصات سرور ناموفق بود.',

            'cpu' => 'دریافت اطلاعات پردازنده ناموفق بود.',

            'resources' => 'دریافت وضعیت منابع سرور ناموفق بود.',

            'services' => 'دریافت فهرست سرویس‌های سیستم ناموفق بود.',

            'docker' => 'دریافت وضعیت Docker ناموفق بود.',

            default => 'دریافت اطلاعات این بخش ناموفق بود.',
        };
    }

    private function cacheKey(
        Server $server,
        string $segment,
    ): string {
        return sprintf(
            '%s:%d:%s',
            self::CACHE_PREFIX,
            (int) $server->getKey(),
            $segment,
        );
    }
}
