<!DOCTYPE html>

<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="rtl"
>
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="color-scheme"
        content="light dark"
    >

    <title>
        {{ isset($title) && $title
            ? $title . ' | ' . config('app.name')
            : config('app.name')
        }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body
    class="
        min-h-screen
        bg-base-200
        font-sans
        text-base-content
        antialiased
    "
>

<x-main full-width>

    {{-- Sidebar --}}
    <x-slot:sidebar
        drawer="panel-drawer"
        collapsible
        collapse-text=""

        class="
            border-s border-base-300
            bg-base-100
        "
    >

        <x-panel.brand />

        <x-panel.navigation />

        <div class="mt-auto">
            <x-panel.footer />
        </div>

    </x-slot:sidebar>


    {{-- Main content --}}
    <x-slot:content>

        <x-panel.header />

        <x-panel.page-container>
            @if(session('payment_verification_pending'))
                @php
                    $paymentVerificationRetryUrl = session(
                        'payment_verification_retry_url',
                    );
                @endphp

                <div
                    role="alert"
                    class="
                        mb-4
                        flex flex-col gap-3
                        rounded-2xl
                        border border-warning/20
                        bg-warning/[0.06]
                        px-4 py-3.5
                        sm:flex-row
                        sm:items-center
                        sm:justify-between
                    "
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="
                                flex size-9 shrink-0
                                items-center justify-center
                                rounded-xl
                                bg-warning/10
                                text-warning
                            "
                        >
                            <x-icon
                                name="lucide.clock-alert"
                                class="!size-4.5"
                            />
                        </div>

                        <div>
                            <div
                                class="text-sm font-semibold text-base-content"
                            >
                                تأیید پرداخت موقتاً در دسترس نیست
                            </div>

                            <p
                                class="mt-1 text-xs leading-6 text-base-content/55"
                            >
                                اگر مبلغ از حساب شما کسر شده است، پرداخت جدید انجام ندهید. چند لحظه بعد دوباره وضعیت همین پرداخت را بررسی کنید.
                            </p>
                        </div>
                    </div>

                    @if(
                        is_string($paymentVerificationRetryUrl)
                        && $paymentVerificationRetryUrl !== ''
                    )
                        <x-button
                            label="بررسی مجدد پرداخت"
                            icon="lucide.refresh-cw"
                            :link="$paymentVerificationRetryUrl"
                            class="
                                btn-warning btn-sm
                                shrink-0 rounded-xl
                            "
                        />
                    @endif
                </div>
            @endif

            {{ $slot }}
        </x-panel.page-container>

    </x-slot:content>

</x-main>


{{-- Global UI --}}
<x-components.panel.offline-indicator />

<x-toast
    position="toast-top toast-center"
/>

</body>
</html>
