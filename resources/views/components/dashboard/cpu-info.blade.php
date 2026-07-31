@props([
    'cpu' => [],
])

@php
    $cpu = array_replace([
        'model' => '—',
        'architecture' => '—',
        'cores' => '—',
        'threads' => '—',
    ], is_array($cpu) ? $cpu : []);

    $displayValue = static function (mixed $value): string {
        if (
            $value === null
            || $value === ''
            || ! is_scalar($value)
        ) {
            return '—';
        }

        return (string) $value;
    };

    $model = $displayValue($cpu['model']);
    $architecture = $displayValue($cpu['architecture']);
    $cores = $displayValue($cpu['cores']);
    $threads = $displayValue($cpu['threads']);
@endphp

<x-dashboard.card
    title="پردازنده"
    subtitle="CPU Information"
    icon="o-cpu-chip"
>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

        {{-- Processor model --}}
        <div
            class="relative overflow-hidden rounded-2xl
                   border border-base-content/5
                   bg-base-200/30
                   p-5
                   lg:col-span-7"
        >
            {{-- Decorative glow --}}
            <div
                class="pointer-events-none absolute -top-12 -left-12
                       size-32 rounded-full bg-primary/10 blur-3xl"
            ></div>

            <div class="relative">

                <div class="flex items-center gap-3">

                    <div
                        class="flex size-10 shrink-0 items-center justify-center
                               rounded-xl border border-primary/10
                               bg-primary/10"
                    >
                        <x-icon
                            name="o-cpu-chip"
                            class="size-5 text-primary"
                        />
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium
                                   text-base-content/45"
                        >
                            مدل پردازنده
                        </p>

                        <p
                            class="mt-0.5 text-[11px]
                                   text-base-content/30"
                        >
                            Processor Model
                        </p>
                    </div>

                </div>

                <div
                    class="mt-5 break-words text-left
                           text-base font-semibold leading-7
                           tracking-tight text-base-content
                           sm:text-lg"
                    dir="ltr"
                >
                    {{ $model }}
                </div>

            </div>
        </div>

        {{-- Processor specifications --}}
        <div
            class="grid grid-cols-3 gap-3
                   lg:col-span-5"
        >

            <div
                class="flex min-w-0 flex-col items-center justify-center
                       rounded-2xl border border-base-content/5
                       bg-base-200/25
                       px-3 py-5 text-center
                       transition duration-300
                       hover:border-primary/15
                       hover:bg-base-200/40"
            >
                <div
                    class="flex size-9 items-center justify-center
                           rounded-xl bg-base-200"
                >
                    <x-icon
                        name="o-command-line"
                        class="size-4 text-base-content/50"
                    />
                </div>

                <div
                    class="mt-3 truncate font-mono text-sm font-semibold
                           text-base-content sm:text-base"
                    dir="ltr"
                >
                    {{ $architecture }}
                </div>

                <div class="mt-1 text-[11px] text-base-content/45">
                    معماری
                </div>
            </div>

            <div
                class="flex min-w-0 flex-col items-center justify-center
                       rounded-2xl border border-base-content/5
                       bg-base-200/25
                       px-3 py-5 text-center
                       transition duration-300
                       hover:border-primary/15
                       hover:bg-base-200/40"
            >
                <div
                    class="flex size-9 items-center justify-center
                           rounded-xl bg-base-200"
                >
                    <x-icon
                        name="o-squares-2x2"
                        class="size-4 text-base-content/50"
                    />
                </div>

                <div
                    class="mt-3 text-base font-semibold
                           text-base-content sm:text-lg"
                >
                    {{ $cores }}
                </div>

                <div class="mt-1 text-[11px] text-base-content/45">
                    هسته
                </div>
            </div>

            <div
                class="flex min-w-0 flex-col items-center justify-center
                       rounded-2xl border border-base-content/5
                       bg-base-200/25
                       px-3 py-5 text-center
                       transition duration-300
                       hover:border-primary/15
                       hover:bg-base-200/40"
            >
                <div
                    class="flex size-9 items-center justify-center
                           rounded-xl bg-base-200"
                >
                    <x-icon
                        name="o-arrows-right-left"
                        class="size-4 text-base-content/50"
                    />
                </div>

                <div
                    class="mt-3 text-base font-semibold
                           text-base-content sm:text-lg"
                >
                    {{ $threads }}
                </div>

                <div class="mt-1 text-[11px] text-base-content/45">
                    رشته
                </div>
            </div>

        </div>

    </div>
</x-dashboard.card>
