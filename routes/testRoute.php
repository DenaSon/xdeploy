<?php

declare(strict_types=1);

use App\Application\Cloud\Networking\AddCloudServerPublicIpAction;
use App\Application\Cloud\Networking\DeleteCloudServerPortAction;
use App\Application\Cloud\Networking\ListCloudServerPortsAction;
use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Application\Cloud\Servers\GetCloudServerActionsAction;
use App\Application\Cloud\Servers\PowerOffCloudServerAction;
use App\Application\Cloud\Servers\PowerOnCloudServerAction;
use App\Application\Cloud\Servers\RebootCloudServerAction;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::match(
    ['GET', 'POST'],
    '/cloud/action-test/{server}',
    function (
        Request $request,
        Server $server,
        CloudServerProvisionerInterface $provisioner,
        PowerOnCloudServerAction $powerOn,
        PowerOffCloudServerAction $powerOff,
        RebootCloudServerAction $reboot,
        GetCloudServerActionsAction $getActions,
        DeleteCloudServerAction $deleteServer,
        ListCloudServerPortsAction $listPorts,
        AddCloudServerPublicIpAction $addPublicIp,
        DeleteCloudServerPortAction $deletePort,
    ) {
        /*
         * این Route به منابع واقعی Cloud دسترسی دارد؛
         * فقط در محیط Local یا با فعال‌سازی صریح قابل استفاده است.
         */
        abort_unless(
            app()->isLocal()
            || config('cloud.discovery_enabled'),
            404,
        );

        $server->refresh();

        $user = User::query()->findOrFail(
            $server->user_id,
        );

        $provider = trim(
            (string) $server->cloud_provider,
        );

        $region = trim(
            (string) $server->cloud_region,
        );

        $providerServerId = trim(
            (string) $server->cloud_server_id,
        );

        abort_if(
            $provider === ''
            || $region === ''
            || $providerServerId === '',
            422,
            'Cloud server metadata is incomplete.',
        );

        /*
         * اجرای عملیات POST
         */
        if ($request->isMethod('POST')) {
            $operation = trim(
                (string) $request->input(
                    'operation',
                ),
            );

            try {
                switch ($operation) {
                    case 'power-on':
                        $powerOn->handle(
                            region: $region,
                            serverId: $providerServerId,
                        );

                        $message = 'درخواست روشن‌کردن ابرک ارسال شد.';

                        break;

                    case 'power-off':
                        $powerOff->handle(
                            region: $region,
                            serverId: $providerServerId,
                        );

                        $message = 'درخواست خاموش‌کردن ابرک ارسال شد.';

                        break;

                    case 'reboot':
                        $reboot->handle(
                            region: $region,
                            serverId: $providerServerId,
                        );

                        $message = 'درخواست ری‌استارت ابرک ارسال شد.';

                        break;

                    case 'add-public-ip':
                        $version = CloudIpVersion::tryFrom(
                            trim(
                                (string) $request->input(
                                    'ip_version',
                                    CloudIpVersion::IPv4->value,
                                ),
                            ),
                        );

                        if ($version === null) {
                            throw ValidationException::withMessages([
                                'ip_version' => 'نسخه IP انتخاب‌شده معتبر نیست.',
                            ]);
                        }

                        $securityGroupIds = array_values(
                            array_unique(
                                array_filter(
                                    array_map(
                                        static fn (
                                            string $id,
                                        ): string => trim($id),
                                        explode(
                                            ',',
                                            (string) $request->input(
                                                'security_group_ids',
                                                '',
                                            ),
                                        ),
                                    ),
                                    static fn (
                                        string $id,
                                    ): bool => $id !== '',
                                ),
                            ),
                        );

                        $addPublicIp->handle(
                            region: $region,
                            serverId: $providerServerId,
                            version: $version,
                            securityGroupIds: $securityGroupIds,
                        );

                        $message = sprintf(
                            'درخواست تخصیص Public %s ارسال شد.',
                            strtoupper($version->value),
                        );

                        break;

                    case 'delete-port':
                        $portId = trim(
                            (string) $request->input(
                                'port_id',
                            ),
                        );

                        if ($portId === '') {
                            throw ValidationException::withMessages([
                                'port_id' => 'شناسه Port الزامی است.',
                            ]);
                        }

                        $updatedServer = $deletePort->handle(
                            user: $user,
                            serverId: (int) $server->getKey(),
                            portId: $portId,
                        );

                        $message = sprintf(
                            'Port حذف شد. Host فعلی xDeploy: %s',
                            $updatedServer->host ?? 'ندارد',
                        );

                        break;

                    case 'delete-server':
                        $confirmation = trim(
                            (string) $request->input(
                                'confirmation',
                            ),
                        );

                        if ($confirmation !== $providerServerId) {
                            throw ValidationException::withMessages([
                                'confirmation' => 'برای حذف ابرک، Provider Server ID را دقیق وارد کنید.',
                            ]);
                        }

                        $deleteServer->handle(
                            user: $user,
                            serverId: (int) $server->getKey(),
                        );

                        return redirect()
                            ->route('cloud.discovery')
                            ->with(
                                'status',
                                'ابرک از Provider و xDeploy حذف شد.',
                            );

                    default:
                        throw ValidationException::withMessages([
                            'operation' => 'عملیات انتخاب‌شده معتبر نیست.',
                        ]);
                }

                return redirect()
                    ->route(
                        'cloud.action-test',
                        [
                            'server' => $server->getKey(),
                        ],
                    )
                    ->with(
                        'cloud_action_message',
                        $message,
                    );
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report(
                    $exception,
                );

                return redirect()
                    ->route(
                        'cloud.action-test',
                        [
                            'server' => $server->getKey(),
                        ],
                    )
                    ->withInput()
                    ->with(
                        'cloud_action_error',
                        $exception->getMessage(),
                    );
            }
        }

        /*
         * دریافت اطلاعات خواندنی؛ شکست یکی از APIها
         * نباید مانع نمایش سایر بخش‌ها شود.
         */
        $safeCall = static function (
            callable $callback,
        ): array {
            try {
                return [
                    'successful' => true,
                    'data' => $callback(),
                    'error' => null,
                ];
            } catch (Throwable $exception) {
                report(
                    $exception,
                );

                return [
                    'successful' => false,
                    'data' => null,
                    'error' => $exception->getMessage(),
                ];
            }
        };

        $providerServer = $safeCall(
            static fn (): mixed => $provisioner->findServer(
                region: $region,
                serverId: $providerServerId,
            ),
        );

        $availableActions = $safeCall(
            static fn (): array => $getActions->handle(
                region: $region,
                serverId: $providerServerId,
            ),
        );

        $ports = $safeCall(
            static fn (): array => $listPorts->handle(
                region: $region,
                serverId: $providerServerId,
            ),
        );

        return Blade::render(
            <<<'BLADE'
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Cloud Actions Test</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="min-h-screen bg-base-200 text-base-content">
    @php
        $prettyJson = static fn (mixed $value): string =>
            json_encode(
                $value,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
            );
    @endphp

    <main class="mx-auto max-w-7xl p-4 md:p-8">
        <header class="mb-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold">
                        تست Actionهای ابرک
                    </h1>

                    <p class="mt-2 text-sm opacity-70">
                        تمام عملیات این صفحه روی ابرک واقعی اجرا می‌شوند.
                    </p>
                </div>

                <a
                    href="{{ url('/cloud/discovery') }}"
                    class="btn btn-ghost"
                >
                    بازگشت به Discovery
                </a>
            </div>
        </header>

        @if (session('cloud_action_message'))
            <div class="alert alert-success mb-6">
                <span>
                    {{ session('cloud_action_message') }}
                </span>
            </div>
        @endif

        @if (session('cloud_action_error'))
            <div class="alert alert-error mb-6">
                <div>
                    <div class="font-bold">
                        عملیات ناموفق بود
                    </div>

                    <div class="mt-1 text-sm">
                        {{ session('cloud_action_error') }}
                    </div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-warning mb-6">
                <ul class="list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="card-title">
                        اطلاعات داخلی xDeploy
                    </h2>

                    <span class="badge badge-primary">
                        Server #{{ $server->getKey() }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs opacity-60">
                            Provider
                        </div>

                        <div class="mt-2 font-medium">
                            {{ $provider }}
                        </div>
                    </div>

                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs opacity-60">
                            Region
                        </div>

                        <div
                            dir="ltr"
                            class="mt-2 text-left font-mono text-sm"
                        >
                            {{ $region }}
                        </div>
                    </div>

                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs opacity-60">
                            Host
                        </div>

                        <div
                            dir="ltr"
                            class="mt-2 text-left font-mono text-sm"
                        >
                            {{ $server->host ?? '—' }}
                        </div>
                    </div>

                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs opacity-60">
                            Local Status
                        </div>

                        <div class="mt-2">
                            {{ $server->status->value }}
                        </div>
                    </div>

                    <div class="rounded-box bg-base-200 p-4 sm:col-span-2 lg:col-span-4">
                        <div class="text-xs opacity-60">
                            Provider Server ID
                        </div>

                        <div
                            dir="ltr"
                            class="mt-2 overflow-auto text-left font-mono text-sm"
                        >
                            {{ $providerServerId }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-3">
            <form
                method="POST"
                class="card bg-base-100 shadow-sm"
            >
                @csrf

                <input
                    type="hidden"
                    name="operation"
                    value="power-on"
                >

                <div class="card-body">
                    <h2 class="card-title">
                        روشن‌کردن
                    </h2>

                    <p class="text-sm opacity-70">
                        اجرای PowerOnCloudServerAction
                    </p>

                    <div class="card-actions mt-auto justify-end">
                        <button class="btn btn-success">
                            Power On
                        </button>
                    </div>
                </div>
            </form>

            <form
                method="POST"
                class="card bg-base-100 shadow-sm"
            >
                @csrf

                <input
                    type="hidden"
                    name="operation"
                    value="power-off"
                >

                <div class="card-body">
                    <h2 class="card-title">
                        خاموش‌کردن
                    </h2>

                    <p class="text-sm opacity-70">
                        اجرای PowerOffCloudServerAction
                    </p>

                    <div class="card-actions mt-auto justify-end">
                        <button class="btn btn-warning">
                            Power Off
                        </button>
                    </div>
                </div>
            </form>

            <form
                method="POST"
                class="card bg-base-100 shadow-sm"
            >
                @csrf

                <input
                    type="hidden"
                    name="operation"
                    value="reboot"
                >

                <div class="card-body">
                    <h2 class="card-title">
                        ری‌استارت
                    </h2>

                    <p class="text-sm opacity-70">
                        اجرای RebootCloudServerAction
                    </p>

                    <div class="card-actions mt-auto justify-end">
                        <button class="btn btn-info">
                            Reboot
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="mt-6 card bg-base-100 shadow-sm">
            <form method="POST" class="card-body">
                @csrf

                <input
                    type="hidden"
                    name="operation"
                    value="add-public-ip"
                >

                <div>
                    <h2 class="card-title">
                        افزودن Public IP
                    </h2>

                    <p class="mt-1 text-sm opacity-70">
                        اجرای AddCloudServerPublicIpAction
                    </p>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text mb-2">
                            نسخه IP
                        </span>

                        <select
                            name="ip_version"
                            class="select select-bordered"
                        >
                            <option value="ipv4">
                                IPv4
                            </option>

                            <option value="ipv6">
                                IPv6
                            </option>
                        </select>
                    </label>

                    <label class="form-control">
                        <span class="label-text mb-2">
                            Security Group IDs
                        </span>

                        <input
                            dir="ltr"
                            type="text"
                            name="security_group_ids"
                            value="8449a4f5-5709-4017-9e63-45496bfe5cc9"
                            class="input input-bordered text-left font-mono text-xs"
                            placeholder="id-1,id-2"
                        >

                        <span class="mt-2 text-xs opacity-60">
                            چند شناسه را با کاما جدا کنید.
                        </span>
                    </label>
                </div>

                <div class="card-actions mt-4 justify-end">
                    <button class="btn btn-primary">
                        افزودن Public IP
                    </button>
                </div>
            </form>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <article class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title">
                                اطلاعات Provider
                            </h2>

                            <p class="text-xs opacity-60">
                                findServer()
                            </p>
                        </div>

                        @if ($providerServer['successful'])
                            <span class="badge badge-success">
                                موفق
                            </span>
                        @else
                            <span class="badge badge-error">
                                ناموفق
                            </span>
                        @endif
                    </div>

                    @if ($providerServer['successful'])
                        <div
                            dir="ltr"
                            class="mt-4 max-h-[32rem] overflow-auto rounded-box bg-neutral p-4 text-neutral-content"
                        >
                            <pre class="text-left text-xs leading-6"><code>{{ $prettyJson(
    $providerServer['data'],
) }}</code></pre>
                        </div>
                    @else
                        <div class="alert alert-error mt-4">
                            {{ $providerServer['error'] }}
                        </div>
                    @endif
                </div>
            </article>

            <article class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title">
                                Actionهای مجاز
                            </h2>

                            <p class="text-xs opacity-60">
                                GetCloudServerActionsAction
                            </p>
                        </div>

                        @if ($availableActions['successful'])
                            <span class="badge badge-success">
                                موفق
                            </span>
                        @else
                            <span class="badge badge-error">
                                ناموفق
                            </span>
                        @endif
                    </div>

                    @if ($availableActions['successful'])
                        <div
                            dir="ltr"
                            class="mt-4 max-h-[32rem] overflow-auto rounded-box bg-neutral p-4 text-neutral-content"
                        >
                            <pre class="text-left text-xs leading-6"><code>{{ $prettyJson(
    $availableActions['data'],
) }}</code></pre>
                        </div>
                    @else
                        <div class="alert alert-error mt-4">
                            {{ $availableActions['error'] }}
                        </div>
                    @endif
                </div>
            </article>
        </section>

        <section class="mt-6 card bg-base-100 shadow-sm">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="card-title">
                            Portها و IPها
                        </h2>

                        <p class="text-xs opacity-60">
                            ListCloudServerPortsAction
                        </p>
                    </div>

                    @if ($ports['successful'])
                        <span class="badge badge-neutral">
                            {{ count($ports['data']) }}
                        </span>
                    @else
                        <span class="badge badge-error">
                            ناموفق
                        </span>
                    @endif
                </div>

                @if (! $ports['successful'])
                    <div class="alert alert-error mt-4">
                        {{ $ports['error'] }}
                    </div>
                @elseif ($ports['data'] === [])
                    <div class="mt-4 rounded-box bg-base-200 p-8 text-center opacity-60">
                        هیچ Portی دریافت نشد.
                    </div>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($ports['data'] as $port)
                            <article class="rounded-box border border-base-300 p-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <div class="text-xs opacity-60">
                                            Port ID
                                        </div>

                                        <div
                                            dir="ltr"
                                            class="mt-1 overflow-auto text-left font-mono text-xs"
                                        >
                                            {{ $port->id }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs opacity-60">
                                            Status
                                        </div>

                                        <div class="mt-1">
                                            {{ $port->status }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs opacity-60">
                                            IPs
                                        </div>

                                        <div class="mt-2 space-y-1">
                                            @foreach ($port->ips as $ip)
                                                <div
                                                    dir="ltr"
                                                    class="text-left font-mono text-xs"
                                                >
                                                    {{ $ip }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs opacity-60">
                                            Network ID
                                        </div>

                                        <div
                                            dir="ltr"
                                            class="mt-1 overflow-auto text-left font-mono text-xs"
                                        >
                                            {{ $port->networkId }}
                                        </div>
                                    </div>
                                </div>

                                <form
                                    method="POST"
                                    class="mt-4 flex justify-end"
                                    onsubmit="return confirm('این Port و IPهای آن واقعاً حذف شوند؟')"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="operation"
                                        value="delete-port"
                                    >

                                    <input
                                        type="hidden"
                                        name="port_id"
                                        value="{{ $port->id }}"
                                    >

                                    <button class="btn btn-error btn-sm">
                                        حذف Port
                                    </button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="mt-6 card border border-error/40 bg-error/5 shadow-sm">
            <form
                method="POST"
                class="card-body"
                onsubmit="return confirm('ابرک از آروان‌کلاد و xDeploy کاملاً حذف شود؟')"
            >
                @csrf

                <input
                    type="hidden"
                    name="operation"
                    value="delete-server"
                >

                <div>
                    <h2 class="card-title text-error">
                        حذف کامل ابرک
                    </h2>

                    <p class="mt-2 text-sm opacity-70">
                        این عملیات ابتدا ابرک را از Provider حذف می‌کند و سپس رکورد داخلی xDeploy را پاک می‌کند.
                    </p>
                </div>

                <label class="form-control mt-4">
                    <span class="label-text mb-2">
                        برای تأیید، Provider Server ID را وارد کنید
                    </span>

                    <input
                        dir="ltr"
                        type="text"
                        name="confirmation"
                        class="input input-bordered input-error text-left font-mono text-xs"
                        placeholder="{{ $providerServerId }}"
                    >
                </label>

                <div class="card-actions mt-4 justify-end">
                    <button class="btn btn-error">
                        حذف کامل ابرک
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
BLADE,
            [
                'server' => $server,
                'user' => $user,
                'provider' => $provider,
                'region' => $region,
                'providerServerId' => $providerServerId,
                'providerServer' => $providerServer,
                'availableActions' => $availableActions,
                'ports' => $ports,
            ],
        );
    },
)->name('cloud.action-test');
