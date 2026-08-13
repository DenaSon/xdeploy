@php
    $applicationIcon = is_string($icon)
        && trim($icon) !== ''
            ? trim($icon)
            : null;

    $usesLucideIcon = $applicationIcon !== null
        && str_starts_with(
            $applicationIcon,
            'lucide.',
        );
@endphp

<x-servers.workspace :server="$server">

    <div
        class="space-y-5"
        aria-busy="true"
        aria-live="polite"
    >
        {{-- Local application context --}}
        <section
            class="overflow-hidden rounded-2xl
                   border border-base-300
                   bg-base-100"
        >
            <div
                class="flex flex-col gap-4
                       px-5 py-5
                       sm:flex-row
                       sm:items-center
                       sm:justify-between
                       sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3.5">
                    <a
                        href="{{ route('panel.servers.applications.index', [
                            'server' => $serverId,
                        ]) }}"
                        wire:navigate
                        aria-label="بازگشت به برنامه‌ها"
                        class="flex size-9 shrink-0
                               items-center justify-center
                               rounded-xl
                               border border-base-300
                               bg-base-100
                               text-base-content/45
                               transition-colors duration-150
                               hover:border-primary/20
                               hover:bg-primary/5
                               hover:text-primary"
                    >
                        <x-icon
                            name="lucide.arrow-right"
                            class="!size-4"
                        />
                    </a>

                    <div
                        class="flex size-12 shrink-0
                               items-center justify-center
                               overflow-hidden rounded-xl
                               border border-primary/15
                               bg-primary/[0.06]"
                    >
                        @if ($usesLucideIcon)

                            <x-icon
                                :name="$applicationIcon"
                                class="!size-5.5 text-primary"
                            />

                        @elseif ($applicationIcon !== null)

                            <img
                                src="{{ asset($applicationIcon) }}"
                                alt="{{ $name }}"
                                class="size-8 object-contain"
                            />

                        @else

                            <x-icon
                                name="lucide.package"
                                class="!size-5.5 text-primary"
                            />

                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="truncate text-lg
                                       font-semibold
                                       text-base-content"
                            >
                                {{ $name }}
                            </h1>

                            <span
                                class="badge badge-info badge-soft
                                       badge-sm gap-1.5"
                            >
                                <span
                                    class="loading loading-spinner
                                           loading-xs"
                                ></span>

                                در حال بررسی
                            </span>
                        </div>

                        @if ($shortDescription !== '')

                            <p
                                class="mt-1 max-w-2xl
                                       text-sm leading-6
                                       text-base-content/50"
                            >
                                {{ $shortDescription }}
                            </p>

                        @endif
                    </div>
                </div>
            </div>

            {{-- Runtime facts skeleton --}}
            <div
                class="grid grid-cols-1
                       border-t border-base-300
                       sm:grid-cols-3"
            >
                @foreach ([
                    ['lucide.activity', 'وضعیت'],
                    ['lucide.fingerprint', 'شناسه برنامه'],
                    ['lucide.tag', 'نسخه'],
                ] as [$factIcon, $factLabel])
                    <div
                        class="flex items-center gap-3
                               border-b border-base-300
                               px-5 py-3.5
                               last:border-b-0
                               sm:border-b-0
                               sm:border-l
                               sm:last:border-l-0
                               sm:px-6"
                    >
                        <div
                            class="flex size-8 shrink-0
                                   items-center justify-center
                                   rounded-lg
                                   bg-base-200/60"
                        >
                            <x-icon
                                :name="$factIcon"
                                class="!size-4 text-base-content/35"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[11px]
                                       text-base-content/40"
                            >
                                {{ $factLabel }}
                            </p>

                            @if ($factLabel === 'شناسه برنامه')
                                <p
                                    dir="ltr"
                                    class="technical-value mt-0.5
                                           truncate text-left
                                           text-sm font-medium
                                           text-base-content/65"
                                >
                                    {{ $application }}
                                </p>
                            @else
                                <div
                                    class="skeleton mt-1.5 h-3.5 w-20"
                                ></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Deferred SSH/runtime loading --}}
        <section
            class="card border border-base-300
                   bg-base-100 shadow-none"
        >
            <div class="card-body gap-5 p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0
                               items-center justify-center
                               rounded-xl bg-info/10
                               text-info"
                    >
                        <span
                            class="loading loading-spinner
                                   loading-sm"
                        ></span>
                    </div>

                    <div class="min-w-0">
                        <h2
                            class="text-sm font-semibold
                                   text-base-content"
                        >
                            در حال دریافت وضعیت برنامه
                        </h2>

                        <p
                            class="mt-1 text-sm leading-6
                                   text-base-content/50"
                        >
                            وضعیت اجرا، نسخه و عملیات در دسترس از سرور بررسی می‌شود.
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="skeleton h-3.5 w-2/3"></div>
                    <div class="skeleton h-3.5 w-1/2"></div>

                    <div class="flex gap-2 pt-1">
                        <div class="skeleton h-8 w-24 rounded-xl"></div>
                        <div class="skeleton h-8 w-20 rounded-xl"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

</x-servers.workspace>
