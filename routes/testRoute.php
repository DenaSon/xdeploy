<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use Illuminate\Http\JsonResponse;


$guardCloudDiscovery = static function (): void {
    abort_unless(
        app()->environment('local')
        && config('cloud.discovery_enabled') === true,
        404,
    );
};

$requiredCloudConfig = static function (string $key): string {
    $value = config($key);

    abort_unless(
        is_string($value) && trim($value) !== '',
        500,
        "Required cloud configuration [{$key}] is missing.",
    );

    return trim($value);
};

$makeArvanProvisionPayload = static function (
    string $serverName,
) use (
    $requiredCloudConfig,
): array {
    $driver = $requiredCloudConfig('cloud.default');

    abort_unless(
        $driver === 'arvan',
        500,
        'The active cloud driver must be ArvanCloud.',
    );

    return [
        'name' => $serverName,

        'network_id' => $requiredCloudConfig(
            'cloud.providers.arvan.defaults.network_id',
        ),

        'flavor_id' => $requiredCloudConfig(
            'cloud.providers.arvan.defaults.size_id',
        ),

        'image_id' => $requiredCloudConfig(
            'cloud.providers.arvan.defaults.image_id',
        ),

        /*
         * با وجود نام فیلد name، API آروان شناسه UUID
         * گروه فایروال را در این قسمت انتظار دارد.
         */
        'security_groups' => [
            [
                'name' => $requiredCloudConfig(
                    'cloud.providers.arvan.defaults.security_group_id',
                ),
            ],
        ],

        /*
         * در این Discovery از Password تولیدشده توسط Provider
         * استفاده می‌کنیم.
         */
        'ssh_key' => false,
        'key_name' => null,

        /*
         * برای جلوگیری از ساخت چند Resource، همیشه یک است.
         */
        'count' => 1,

        'create_type' => $requiredCloudConfig(
            'cloud.providers.arvan.defaults.create_type',
        ),

        'disk_size' => (int) config(
            'cloud.providers.arvan.defaults.disk_size',
            30,
        ),

        'init_script' => '',
        'ha_enabled' => false,
    ];
};

$sanitizeCloudResponse = static function (array $payload): array {
    $sanitized = $payload;

    $sensitiveKeys = [
        'password',
        'root_password',
        'admin_password',
        'token',
        'api_key',
        'authorization',
        'private_key',
        'secret',
    ];

    array_walk_recursive(
        $sanitized,
        static function (
            mixed &$value,
            string|int $key,
        ) use (
            $sensitiveKeys,
        ): void {
            if (
                ! in_array(
                    strtolower((string) $key),
                    $sensitiveKeys,
                    true,
                )
            ) {
                return;
            }

            if (is_string($value) && $value !== '') {
                $value = sprintf(
                    '[REDACTED:%d chars]',
                    strlen($value),
                );

                return;
            }

            $value = '[REDACTED]';
        },
    );

    return $sanitized;
};

$firstScalarValue = static function (
    array $payload,
    array $paths,
): string|int|float|bool|null {
    foreach ($paths as $path) {
        $value = data_get($payload, $path);

        if (is_scalar($value)) {
            return $value;
        }
    }

    return null;
};

/*
|--------------------------------------------------------------------------
| Discovery page
|--------------------------------------------------------------------------
|
| Payload دقیق نمایش‌داده‌شده داخل Cache قرار می‌گیرد.
| POST فقط همان Payload را یک‌بار مصرف می‌کند.
|
*/

Route::get(
    '/dev/arvan/provision',
    static function () use (
        $guardCloudDiscovery,
        $makeArvanProvisionPayload,
    ) {
        $guardCloudDiscovery();

        $serverName = sprintf(
            'xdeploy-discovery-%s-%s',
            now()->format('Ymd-His'),
            Str::lower(Str::random(6)),
        );

        $payload = $makeArvanProvisionPayload($serverName);

        $operationToken = Str::uuid()->toString();

        Cache::put(
            "arvan-discovery:operation:{$operationToken}",
            $payload,
            now()->addMinutes(10),
        );

        $formattedPayload = htmlspecialchars(
            json_encode(
                $payload,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR,
            ),
            ENT_QUOTES,
            'UTF-8',
        );

        $action = htmlspecialchars(
            route('dev.arvan.provision.store'),
            ENT_QUOTES,
            'UTF-8',
        );

        $csrfToken = htmlspecialchars(
            csrf_token(),
            ENT_QUOTES,
            'UTF-8',
        );

        $escapedOperationToken = htmlspecialchars(
            $operationToken,
            ENT_QUOTES,
            'UTF-8',
        );

        return response(
            <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">

                <meta
                    name="viewport"
                    content="width=device-width, initial-scale=1"
                >

                <title>ArvanCloud Provision Discovery</title>

                <style>
                    body {
                        max-width: 900px;
                        margin: 40px auto;
                        padding: 0 20px;
                        color: #e5e7eb;
                        background: #111827;
                        font-family: ui-monospace, monospace;
                    }

                    pre {
                        overflow-x: auto;
                        padding: 20px;
                        border: 1px solid #374151;
                        border-radius: 12px;
                        background: #1f2937;
                    }

                    button {
                        padding: 12px 18px;
                        border: 0;
                        border-radius: 10px;
                        color: white;
                        background: #dc2626;
                        cursor: pointer;
                        font-weight: 700;
                    }

                    button:disabled {
                        cursor: not-allowed;
                        opacity: 0.5;
                    }

                    .warning {
                        margin: 20px 0;
                        padding: 16px;
                        border: 1px solid #92400e;
                        border-radius: 12px;
                        background: #451a03;
                    }
                </style>
            </head>

            <body>
                <h1>ArvanCloud Provision Discovery</h1>

                <div class="warning">
                    This action creates one real, billable cloud server.
                    The operation token can only be used once.
                </div>

                <h2>Request Payload</h2>

                <pre>{$formattedPayload}</pre>

                <form
                    method="POST"
                    action="{$action}"
                    onsubmit="
                        const button = this.querySelector('button');

                        if (
                            ! confirm(
                                'Create one real ArvanCloud server?'
                            )
                        ) {
                            return false;
                        }

                        button.disabled = true;
                        button.textContent = 'Creating server...';

                        return true;
                    "
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="{$csrfToken}"
                    >

                    <input
                        type="hidden"
                        name="operation_token"
                        value="{$escapedOperationToken}"
                    >

                    <button type="submit">
                        Create Discovery Server
                    </button>
                </form>
            </body>
            </html>
            HTML,
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ],
        );
    },
)->name('dev.arvan.provision.index');

/*
|--------------------------------------------------------------------------
| Create real server
|--------------------------------------------------------------------------
*/

Route::post(
    '/dev/arvan/provision',
    static function (
        Request $request,
    ) use (
        $guardCloudDiscovery,
        $requiredCloudConfig,
        $sanitizeCloudResponse,
        $firstScalarValue,
    ) {
        $guardCloudDiscovery();

        $validated = $request->validate([
            'operation_token' => [
                'required',
                'uuid',
            ],
        ]);

        $operationToken = $validated['operation_token'];

        /*
         * pull باعث می‌شود Token فقط یک‌بار قابل استفاده باشد.
         */
        $payload = Cache::pull(
            "arvan-discovery:operation:{$operationToken}",
        );

        abort_unless(
            is_array($payload),
            419,
            'Discovery operation expired or was already used.',
        );

        /*
         * از اجرای هم‌زمان دو درخواست Provision جلوگیری می‌کند.
         */
        $lock = Cache::lock(
            'arvan-discovery:provision-lock',
            120,
        );

        abort_unless(
            $lock->get(),
            409,
            'Another ArvanCloud discovery operation is running.',
        );

        try {
            $baseUrl = rtrim(
                $requiredCloudConfig(
                    'cloud.providers.arvan.base_url',
                ),
                '/',
            );

            abort_unless(
                str_starts_with($baseUrl, 'https://'),
                500,
                'ArvanCloud base URL must use HTTPS.',
            );

            $apiKey = $requiredCloudConfig(
                'cloud.providers.arvan.api_key',
            );

            $region = $requiredCloudConfig(
                'cloud.providers.arvan.region',
            );

            $connectTimeout = (int) config(
                'cloud.providers.arvan.connect_timeout',
                10,
            );

            $timeout = (int) config(
                'cloud.providers.arvan.timeout',
                90,
            );

            $endpoint = sprintf(
                '%s/regions/%s/servers',
                $baseUrl,
                rawurlencode($region),
            );

            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->withHeaders([
                        'Authorization' => 'Apikey '.$apiKey,
                    ])
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->withoutRedirecting()
                    ->post(
                        $endpoint,
                        $payload,
                    );
            } catch (ConnectionException $exception) {
                report($exception);

                return response()->json(
                    [
                        'successful' => false,

                        'error' => [
                            'type' => 'connection_error',
                            'message' => 'Could not connect to ArvanCloud.',
                        ],
                    ],
                    502,
                    [
                        'Cache-Control' => 'no-store, private',
                    ],
                );
            }

            $rawBody = $response->body();

            try {
                $decodedBody = json_decode(
                    $rawBody,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                $decodedBody = [
                    '_non_json_response' => true,
                    'length' => strlen($rawBody),
                    'sha256' => hash('sha256', $rawBody),
                ];
            }

            if (! is_array($decodedBody)) {
                $decodedBody = [
                    '_unexpected_response_type' => get_debug_type(
                        $decodedBody,
                    ),
                ];
            }

            /*
             * پاسخ کامل، شامل Password احتمالی، رمزگذاری می‌شود.
             * Authorization Header یا API Key داخل Capture قرار نمی‌گیرد.
             */
            $capture = [
                'captured_at' => now()->toIso8601String(),

                'request' => [
                    'method' => 'POST',

                    'endpoint' => sprintf(
                        '/regions/%s/servers',
                        $region,
                    ),

                    'payload' => $payload,
                ],

                'response' => [
                    'status' => $response->status(),
                    'body' => $decodedBody,
                    'raw_body' => $rawBody,
                ],
            ];

            $capturePath = sprintf(
                'arvan-discovery/%s-%s.enc',
                now()->format('Ymd-His'),
                Str::uuid()->toString(),
            );

            $captureSaved = true;
            $captureError = null;

            try {
                Storage::disk('local')->put(
                    $capturePath,
                    Crypt::encryptString(
                        json_encode(
                            $capture,
                            JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                            | JSON_THROW_ON_ERROR,
                        ),
                    ),
                );

                Storage::disk('local')->put(
                    'arvan-discovery/latest.txt',
                    $capturePath,
                );
            } catch (Throwable $exception) {
                report($exception);

                $captureSaved = false;
                $captureError =
                    'The encrypted response capture could not be saved.';
            }

            /*
             * مسیرهای احتمالی پاسخ تا زمان مشاهده Schema واقعی.
             */
            $detectedServerId = $firstScalarValue(
                $decodedBody,
                [
                    'id',
                    'data.id',
                    'server.id',
                    'data.server.id',
                    'servers.0.id',
                    'data.servers.0.id',
                    'data.0.id',
                    'result.id',
                    'result.0.id',
                ],
            );

            $detectedStatus = $firstScalarValue(
                $decodedBody,
                [
                    'status',
                    'data.status',
                    'server.status',
                    'data.server.status',
                    'servers.0.status',
                    'data.servers.0.status',
                    'data.0.status',
                    'result.status',
                    'result.0.status',
                ],
            );

            $detectedPassword = $firstScalarValue(
                $decodedBody,
                [
                    'password',
                    'data.password',
                    'server.password',
                    'data.server.password',
                    'servers.0.password',
                    'data.servers.0.password',
                    'data.0.password',
                    'result.password',
                    'result.0.password',
                ],
            );

            $detectedPassword = is_string($detectedPassword)
                ? $detectedPassword
                : null;

            return response()->json(
                [
                    'request' => [
                        'method' => 'POST',

                        'endpoint' => sprintf(
                            '/regions/%s/servers',
                            $region,
                        ),

                        'payload' => $payload,
                    ],

                    'response' => [
                        'status' => $response->status(),
                        'successful' => $response->successful(),

                        'body' => $sanitizeCloudResponse(
                            $decodedBody,
                        ),
                    ],

                    'detected' => [
                        'server_id' => $detectedServerId !== null
                            ? (string) $detectedServerId
                            : null,

                        'status' => $detectedStatus !== null
                            ? (string) $detectedStatus
                            : null,

                        'password_present' => $detectedPassword !== null
                            && $detectedPassword !== '',

                        'password_length' => $detectedPassword !== null
                                ? strlen($detectedPassword)
                                : null,
                    ],

                    'encrypted_capture' => [
                        'saved' => $captureSaved,

                        'disk' => $captureSaved
                            ? 'local'
                            : null,

                        'path' => $captureSaved
                            ? $capturePath
                            : null,

                        'error' => $captureError,
                    ],

                    'next_step' => $response->successful()
                        ? 'Copy this sanitized response. Do not submit again.'
                        : 'Review the sanitized provider error.',
                ],
                $response->successful()
                    ? 201
                    : $response->status(),
                [
                    'Cache-Control' => 'no-store, private',
                ],
            );
        } finally {
            $lock->release();
        }
    },
)->name('dev.arvan.provision.store');



Route::get(
    '/dev/arvan/servers/{serverId}',
    function (string $serverId): JsonResponse {
        abort_unless(
            app()->environment('local')
            && (bool) config('cloud.discovery_enabled'),
            404,
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        if (! Str::isUuid($serverId)) {
            return response()->json(
                [
                    'successful' => false,
                    'message' => 'The server ID must be a valid UUID.',
                ],
                422,
            );
        }

        $region = config(
            'cloud.providers.arvan.region',
        );

        if (
            ! is_string($region)
            || trim($region) === ''
        ) {
            return response()->json(
                [
                    'successful' => false,
                    'message' => 'ArvanCloud region is not configured.',
                ],
                500,
            );
        }

        $region = trim($region);
        $endpoint = "regions/{$region}/servers";

        /*
        |--------------------------------------------------------------------------
        | Sanitize Provider Response
        |--------------------------------------------------------------------------
        */

        $sanitize = function (
            mixed $value,
        ) use (
            &$sanitize,
        ): mixed {
            if (! is_array($value)) {
                return $value;
            }

            $sanitized = [];

            foreach ($value as $key => $child) {
                $normalizedKey = strtolower(
                    (string) $key,
                );

                $isSensitive = in_array(
                        $normalizedKey,
                        [
                            'password',
                            'root_password',
                            'admin_password',
                            'token',
                            'access_token',
                            'refresh_token',
                            'api_key',
                            'authorization',
                            'secret',
                            'private_key',
                            'credential',
                        ],
                        true,
                    )
                    || str_ends_with(
                        $normalizedKey,
                        '_password',
                    )
                    || str_ends_with(
                        $normalizedKey,
                        '_token',
                    )
                    || str_ends_with(
                        $normalizedKey,
                        '_secret',
                    )
                    || str_ends_with(
                        $normalizedKey,
                        '_private_key',
                    )
                    || str_ends_with(
                        $normalizedKey,
                        '_credential',
                    );

                $sanitized[$key] = $isSensitive
                    ? '[REDACTED]'
                    : $sanitize($child);
            }

            return $sanitized;
        };

        /*
        |--------------------------------------------------------------------------
        | Find Server Recursively
        |--------------------------------------------------------------------------
        */

        $findServer = function (
            mixed $value,
        ) use (
            &$findServer,
            $serverId,
        ): ?array {
            if (! is_array($value)) {
                return null;
            }

            if (
                array_key_exists('id', $value)
                && (string) $value['id'] === $serverId
            ) {
                return $value;
            }

            foreach ($value as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $server = $findServer($child);

                if ($server !== null) {
                    return $server;
                }
            }

            return null;
        };

        /*
        |--------------------------------------------------------------------------
        | Request Provider
        |--------------------------------------------------------------------------
        */

        try {
            $providerResponse = app(
                ArvanCloudClient::class,
            )->get($endpoint);
        } catch (Throwable $exception) {
            $status = $exception->getCode();

            if (
                ! is_int($status)
                || $status < 400
                || $status > 599
            ) {
                $status = 502;
            }

            return response()->json(
                [
                    'request' => [
                        'method' => 'GET',
                        'endpoint' => "/{$endpoint}",
                        'region' => $region,
                        'server_id' => $serverId,
                    ],

                    'response' => [
                        'successful' => false,
                        'status' => $status,
                    ],

                    'error' => [
                        'type' => class_basename(
                            $exception,
                        ),
                        'message' => $exception->getMessage(),
                    ],
                ],
                $status,
            );
        }

        $server = $findServer(
            $providerResponse,
        );

        if ($server === null) {
            return response()->json(
                [
                    'request' => [
                        'method' => 'GET',
                        'endpoint' => "/{$endpoint}",
                        'region' => $region,
                        'server_id' => $serverId,
                    ],

                    'response' => [
                        'successful' => true,
                        'server_found' => false,
                        'top_level_keys' => array_keys(
                            $providerResponse,
                        ),
                        'sanitized_body' => $sanitize(
                            $providerResponse,
                        ),
                    ],

                    'message' => 'The requested server was not found in the provider response.',
                ],
                404,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Addresses
        |--------------------------------------------------------------------------
        */

        $rawAddresses = data_get(
            $server,
            'addresses',
            [],
        );

        $addresses = collect(
            is_array($rawAddresses)
                ? $rawAddresses
                : [],
        )->flatMap(
            static function (
                mixed $items,
                string|int $networkKey,
            ): array {
                if (! is_array($items)) {
                    return [];
                }

                return collect($items)
                    ->filter(
                        static fn (mixed $item): bool =>
                        is_array($item),
                    )
                    ->map(
                        static function (
                            array $item,
                        ) use (
                            $networkKey,
                        ): array {
                            $address = isset($item['addr'])
                                ? trim((string) $item['addr'])
                                : null;

                            return [
                                'network_key' => (string) $networkKey,
                                'address' => $address,
                                'version' => isset($item['version'])
                                    ? (int) $item['version']
                                    : null,
                                'type' => isset($item['type'])
                                    ? (string) $item['type']
                                    : null,
                                'is_public' => (bool) (
                                    $item['is_public']
                                    ?? false
                                ),
                                'is_vpc' => (bool) (
                                    $item['is_vpc']
                                    ?? false
                                ),
                                'mac_address' => isset(
                                    $item['mac_addr'],
                                )
                                    ? (string) $item['mac_addr']
                                    : null,
                            ];
                        },
                    )
                    ->values()
                    ->all();
            },
        )->filter(
            static fn (array $address): bool =>
                is_string($address['address'])
                && filter_var(
                    $address['address'],
                    FILTER_VALIDATE_IP,
                ) !== false,
        )->unique(
            static fn (array $address): string =>
            implode(
                '|',
                [
                    $address['address'],
                    $address['mac_address'] ?? '',
                    $address['network_key'],
                ],
            ),
        )->values();

        /*
        |--------------------------------------------------------------------------
        | Detect Public and Private Addresses
        |--------------------------------------------------------------------------
        */

        $publicIpv4s = $addresses
            ->filter(
                static fn (array $address): bool =>
                    $address['version'] === 4
                    && $address['is_public'] === true
                    && filter_var(
                        $address['address'],
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4,
                    ) !== false,
            )
            ->pluck('address')
            ->unique()
            ->values()
            ->all();

        $privateIpv4s = $addresses
            ->filter(
                static fn (array $address): bool =>
                    $address['version'] === 4
                    && $address['is_public'] === false
                    && filter_var(
                        $address['address'],
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV4,
                    ) !== false,
            )
            ->pluck('address')
            ->unique()
            ->values()
            ->all();

        $publicIpv6s = $addresses
            ->filter(
                static fn (array $address): bool =>
                    $address['version'] === 6
                    && $address['is_public'] === true
                    && filter_var(
                        $address['address'],
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV6,
                    ) !== false,
            )
            ->pluck('address')
            ->unique()
            ->values()
            ->all();

        $privateIpv6s = $addresses
            ->filter(
                static fn (array $address): bool =>
                    $address['version'] === 6
                    && $address['is_public'] === false
                    && filter_var(
                        $address['address'],
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_IPV6,
                    ) !== false,
            )
            ->pluck('address')
            ->unique()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Normalize Network Attachments
        |--------------------------------------------------------------------------
        */

        $networkAttachmentIds = collect(
            data_get(
                $server,
                'networks',
                [],
            ),
        )->filter(
            static fn (mixed $networkId): bool =>
                is_string($networkId)
                && trim($networkId) !== '',
        )->map(
            static fn (string $networkId): string =>
            trim($networkId),
        )->unique()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Normalize Security Groups
        |--------------------------------------------------------------------------
        */

        $securityGroups = collect(
            data_get(
                $server,
                'security_groups',
                [],
            ),
        )->filter(
            static fn (mixed $group): bool =>
            is_array($group),
        )->unique(
            static fn (array $group): string =>
            (string) (
                $group['id']
                ?? $group['name']
                ?? ''
            ),
        )->values();

        $securityGroupIds = $securityGroups
            ->pluck('id')
            ->filter(
                static fn (mixed $id): bool =>
                    is_string($id)
                    && trim($id) !== '',
            )
            ->map(
                static fn (string $id): string =>
                trim($id),
            )
            ->unique()
            ->values()
            ->all();

        $securityGroupNames = $securityGroups
            ->pluck('name')
            ->filter(
                static fn (mixed $name): bool =>
                    is_string($name)
                    && trim($name) !== '',
            )
            ->map(
                static fn (string $name): string =>
                trim($name),
            )
            ->unique()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Normalize Flavor
        |--------------------------------------------------------------------------
        */

        $flavor = data_get(
            $server,
            'flavor',
            [],
        );

        $flavor = is_array($flavor)
            ? $flavor
            : [];

        /*
        |--------------------------------------------------------------------------
        | Normalize Image
        |--------------------------------------------------------------------------
        */

        $image = data_get(
            $server,
            'image',
            [],
        );

        $image = is_array($image)
            ? $image
            : [];

        /*
        |--------------------------------------------------------------------------
        | Build Discovery Response
        |--------------------------------------------------------------------------
        */

        $result = [
            'request' => [
                'method' => 'GET',
                'endpoint' => "/{$endpoint}",
                'region' => $region,
                'server_id' => $serverId,
            ],

            'response' => [
                'successful' => true,
                'server_found' => true,
                'top_level_keys' => array_keys(
                    $providerResponse,
                ),
            ],

            'detected' => [
                'id' => data_get(
                    $server,
                    'id',
                ),

                'name' => data_get(
                    $server,
                    'name',
                ),

                'status' => data_get(
                    $server,
                    'status',
                ),

                'task_state' => data_get(
                    $server,
                    'task_state',
                ),

                'created_at' => data_get(
                    $server,
                    'created',
                ),

                /*
                 * Provider does not explicitly identify a primary IP.
                 * The first address is retained only as a convenience.
                 */
                'first_public_ipv4' => $publicIpv4s[0]
                    ?? null,

                'public_ipv4s' => $publicIpv4s,

                'public_ipv4_count' => count(
                    $publicIpv4s,
                ),

                'private_ipv4s' => $privateIpv4s,

                'public_ipv6s' => $publicIpv6s,

                'private_ipv6s' => $privateIpv6s,

                'ssh_username' => data_get(
                    $server,
                    'image.username',
                ) ?: data_get(
                    $server,
                    'image.metadata.username',
                ) ?: data_get(
                    $server,
                    'username',
                ),

                'flavor' => [
                    'id' => $flavor['id']
                        ?? null,

                    'name' => $flavor['name']
                        ?? null,

                    'vcpus' => isset($flavor['vcpus'])
                        ? (int) $flavor['vcpus']
                        : null,

                    'ram_mib' => isset($flavor['ram'])
                        ? (int) $flavor['ram']
                        : null,

                    'disk_gib' => isset($flavor['disk'])
                        ? (int) $flavor['disk']
                        : null,

                    'swap' => $flavor['swap']
                        ?? null,

                    'free_server' => isset(
                        $flavor['free_server'],
                    )
                        ? (bool) $flavor['free_server']
                        : null,
                ],

                'image' => [
                    'id' => $image['id']
                        ?? null,

                    'name' => $image['name']
                        ?? null,

                    'os' => $image['os']
                        ?? null,

                    'version' => $image['os_version']
                        ?? null,

                    'status' => $image['status']
                        ?? null,

                    'username' => $image['username']
                        ?? data_get(
                            $image,
                            'metadata.username',
                        ),
                ],

                'network_attachment_ids' =>
                    $networkAttachmentIds,

                'security_group_ids' =>
                    $securityGroupIds,

                'security_group_names' =>
                    $securityGroupNames,

                'volume_backed' => data_get(
                    $server,
                    'volume_backed',
                ),

                'ha_enabled' => data_get(
                    $server,
                    'ha_enabled',
                ),

                'backup_enabled' => data_get(
                    $server,
                    'backup_enabled',
                ),

                'spot' => data_get(
                    $server,
                    'spot',
                ),

                'domain' => data_get(
                    $server,
                    'domain',
                ),
            ],

            'provider_fields' => [
                'addresses' => $addresses->all(),

                'network_attachment_ids' =>
                    $networkAttachmentIds,

                'security_groups' => $sanitize(
                    $securityGroups->all(),
                ),

                'flavor' => $sanitize(
                    $flavor,
                ),

                'image' => $sanitize(
                    $image,
                ),

                'tags' => $sanitize(
                    data_get(
                        $server,
                        'tags',
                        [],
                    ),
                ),
            ],

            /*
             * Full sanitized provider record for discovery only.
             */
            'server_record' => $sanitize(
                $server,
            ),
        ];

        return response()->json(
            $result,
            200,
            [],
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    },
)->whereUuid('serverId')
    ->name('dev.arvan.servers.show');
