<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>xDeploy Cloud Test</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="min-h-screen bg-base-200">

<div class="mx-auto max-w-6xl px-6 py-10">

    {{-- Header --}}
    <div class="mb-8">

        <h1 class="text-2xl font-bold">
            تست Provisioning ابر آروان
        </h1>

        <p class="mt-2 text-sm text-base-content/60">
            انتخاب پلن، ساخت ابرک و اجرای کامل SSH Readiness
        </p>

    </div>

    {{-- Warning --}}
    <div
        role="alert"
        class="alert alert-warning mb-6"
    >
        <span>
            با زدن دکمه ساخت، یک ابرک واقعی در ابر آروان ایجاد می‌شود
            و ممکن است هزینه داشته باشد.
        </span>
    </div>

    {{-- Success --}}
    @if (session('success'))

        <div
            role="alert"
            class="alert alert-success mb-6"
        >
            <span>
                {{ session('success') }}
            </span>
        </div>

    @endif

    {{-- Error --}}
    @if (session('error'))

        <div
            role="alert"
            class="alert alert-error mb-6"
        >
            <div>
                <div class="font-semibold">
                    Provisioning failed
                </div>

                <div
                    dir="ltr"
                    class="mt-1 font-mono text-xs"
                >
                    {{ session('error') }}
                </div>
            </div>
        </div>

    @endif

    {{-- Region --}}
    <div
        class="card mb-6 border border-base-300
               bg-base-100 shadow-sm"
    >
        <div class="card-body">

            <h2 class="card-title text-base">
                Region
            </h2>

            <form
                method="GET"
                action="{{ route('dev.cloud-test') }}"
            >
                <select
                    name="region"
                    class="select select-bordered w-full"
                    onchange="this.form.submit()"
                >

                    @foreach ($regions as $region)

                        <option
                            value="{{ $region->id }}"
                            @selected(
                                $regionId === $region->id
                            )
                        >
                            {{ $region->displayName ?? $region->id }}

                            @if ($region->city)
                                — {{ $region->city }}
                            @endif
                        </option>

                    @endforeach

                </select>
            </form>

        </div>
    </div>

    {{-- Plans --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">

        @forelse ($sizes as $size)

            <div
                class="card border border-base-300
                       bg-base-100 shadow-sm"
            >
                <div class="card-body">

                    <div
                        class="flex items-start
                               justify-between gap-4"
                    >
                        <div>

                            <h2 class="card-title">
                                {{ $size->name }}
                            </h2>

                            <div
                                dir="ltr"
                                class="mt-1 font-mono
                                       text-xs text-base-content/45"
                            >
                                {{ $size->id }}
                            </div>

                        </div>

                        @if ($size->category)

                            <span
                                class="badge badge-ghost"
                            >
                                {{ $size->category }}
                            </span>

                        @endif

                    </div>

                    <div
                        class="my-4 grid grid-cols-3
                               gap-2 text-center"
                    >

                        <div
                            class="rounded-xl
                                   bg-base-200 p-3"
                        >
                            <div class="font-bold">
                                {{ $size->vCpu }}
                            </div>

                            <div
                                class="mt-1 text-xs
                                       text-base-content/50"
                            >
                                vCPU
                            </div>
                        </div>

                        <div
                            class="rounded-xl
                                   bg-base-200 p-3"
                        >
                            <div class="font-bold">
                                {{ number_format(
                                    $size->memoryMiB / 1024,
                                    1
                                ) }}
                            </div>

                            <div
                                class="mt-1 text-xs
                                       text-base-content/50"
                            >
                                GB RAM
                            </div>
                        </div>

                        <div
                            class="rounded-xl
                                   bg-base-200 p-3"
                        >
                            <div class="font-bold">
                                {{ $size->diskGiB }}
                            </div>

                            <div
                                class="mt-1 text-xs
                                       text-base-content/50"
                            >
                                GB Disk
                            </div>
                        </div>

                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'dev.cloud-test.create'
                        ) }}"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="region"
                            value="{{ $regionId }}"
                        >

                        <input
                            type="hidden"
                            name="size"
                            value="{{ $size->id }}"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary w-full"
                            onclick="
                                return confirm(
                                    'ابرک واقعی ساخته شود؟'
                                )
                            "
                        >
                            ساخت و تست SSH
                        </button>

                    </form>

                </div>
            </div>

        @empty

            <div
                class="col-span-full
                       rounded-xl bg-base-100
                       p-8 text-center
                       text-base-content/50"
            >
                پلنی برای این Region پیدا نشد.
            </div>

        @endforelse

    </div>

</div>

</body>

</html>
