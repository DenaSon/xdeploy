<?php

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get(
    '/dev/test-ssh-readiness/{server}',
    function (
        Server $server,
        VerifyCloudServerSshReadinessAction $action,
    ) {
        abort_unless(
            app()->environment('local'),
            404,
        );

        try {
            $mode = $action->handle(
                $server,
            );

            return response()->json([
                'success' => true,
                'server_id' => $server->id,
                'host' => $server->host,
                'status' => $server->refresh()->status,
                'privileged_mode' => $mode->value,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
            ], 500);
        }
    },
);

Route::middleware(['web', 'auth'])
    ->prefix('dev')
    ->group(function (): void {

        Route::get(
            '/test',
            function (
                Request $request,
                CloudProviderInterface $catalog,
            ) {
                abort_unless(
                    app()->environment('local'),
                    404,
                );

                $regions = collect(
                    $catalog->listRegions(),
                )
                    ->filter(
                        fn ($region) => $region->isVisible
                            && $region->canCreateServers,
                    )
                    ->values();

                $regionId = trim(
                    (string) $request->query(
                        'region',
                        $regions->first()?->id ?? '',
                    ),
                );

                $sizes = collect();

                if ($regionId !== '') {
                    $sizes = collect(
                        $catalog->listSizes(
                            $regionId,
                        ),
                    )->values();
                }

                return view(
                    'dev.cloud-test',
                    [
                        'regions' => $regions,
                        'regionId' => $regionId,
                        'sizes' => $sizes,
                    ],
                );
            },
        )->name('dev.cloud-test');

        Route::post(
            '/cloud-test',
            function (
                Request $request,
                CloudProviderInterface $catalog,
                ProvisionCloudServerAction $provision,
            ) {
                abort_unless(
                    app()->environment('local'),
                    404,
                );

                $validated = $request->validate([
                    'region' => [
                        'required',
                        'string',
                    ],

                    'size' => [
                        'required',
                        'string',
                    ],
                ]);

                $regionId = trim(
                    $validated['region'],
                );

                $sizeId = trim(
                    $validated['size'],
                );

                /*
                 * Find selected size.
                 */
                $size = collect(
                    $catalog->listSizes(
                        $regionId,
                    ),
                )->first(
                    fn ($size) => $size->id === $sizeId,
                );

                abort_if(
                    $size === null,
                    422,
                    'Cloud size not found.',
                );

                /*
                 * Prefer Ubuntu 24.04 because this is the image
                 * we specifically want to test for forced
                 * password-change behaviour.
                 */
                $images = collect(
                    $catalog->listImages(
                        $regionId,
                    ),
                )->filter(
                    fn ($image) => $image->supportsPassword,
                );

                $image = $images->first(
                    fn ($image) => strcasecmp(
                        $image->distribution,
                        'ubuntu',
                    ) === 0
                        && str_starts_with(
                            $image->version,
                            '24.04',
                        ),
                );

                $image ??= $images->first(
                    fn ($image) => strcasecmp(
                        $image->distribution,
                        'ubuntu',
                    ) === 0,
                );

                abort_if(
                    $image === null,
                    422,
                    'No password-compatible Ubuntu image found.',
                );

                /*
                 * Find active IPv4 network.
                 */
                $network = collect(
                    $catalog->listNetworks(
                        $regionId,
                    ),
                )->first(
                    fn ($network) => $network->isActive
                        && $network->ipVersion
                        === CloudIpVersion::IPv4,
                );

                abort_if(
                    $network === null,
                    422,
                    'No active IPv4 network found.',
                );

                /*
                 * Prefer default security group.
                 */
                $securityGroups = collect(
                    $catalog->listSecurityGroups(
                        $regionId,
                    ),
                );

                $securityGroup =
                    $securityGroups->first(
                        fn ($group) => $group->isDefault,
                    )
                    ?? $securityGroups->first();

                abort_if(
                    $securityGroup === null,
                    422,
                    'No security group found.',
                );

                /*
                 * Image minimum disk requirement may be
                 * larger than the plan's default disk.
                 */
                $diskGiB = max(
                    $size->diskGiB,
                    $image->minDiskGiB ?? 0,
                );

                if (
                    $image->minMemoryMiB !== null
                    && $size->memoryMiB
                    < $image->minMemoryMiB
                ) {
                    return back()->with(
                        'error',
                        'پلن انتخاب‌شده RAM کافی برای Image انتخاب‌شده ندارد.',
                    );
                }

                try {
                    $result = $provision->handle(
                        user: $request->user(),

                        data: new CreateCloudServerData(
                            name: 'xdeploy-test-'
                            .now()->format(
                                'Ymd-His',
                            ),

                            regionId: $regionId,

                            sizeId: $size->id,

                            imageId: $image->id,

                            networkId: $network->id,

                            securityGroupIds: [
                                $securityGroup->id,
                            ],

                            diskGiB: $diskGiB,
                        ),
                    );
                } catch (Throwable $exception) {
                    report(
                        $exception,
                    );

                    return redirect()
                        ->route(
                            'dev.cloud-test',
                            [
                                'region' => $regionId,
                            ],
                        )
                        ->with(
                            'error',
                            sprintf(
                                '%s: %s',
                                $exception::class,
                                $exception->getMessage(),
                            ),
                        );
                }

                return redirect()
                    ->route(
                        'dev.cloud-test',
                        [
                            'region' => $regionId,
                        ],
                    )
                    ->with(
                        'success',
                        sprintf(
                            'ابرک ساخته شد. Server ID: %s | IP: %s',
                            $result->server->id,
                            $result->server->host,
                        ),
                    );
            },
        )->name('dev.cloud-test.create');
    });
