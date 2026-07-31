<div>
    <x-header title="برنامه‌ها" />

    <div class="mt-6">

        @if ($serverMissing)

            <x-card class="border border-warning/20 bg-warning/5 py-12 text-center">

                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-warning/10"
                >
                    <x-icon
                        name="o-server"
                        class="size-7 text-warning"
                    />
                </div>

                <h3 class="mt-4 text-lg font-semibold text-base-content">
                    سرور فعالی وجود ندارد
                </h3>

                <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-base-content/60">
                    برای مشاهده و مدیریت برنامه‌ها، ابتدا باید یک سرور فعال در
                    xDeploy تعریف شود.
                </p>

            </x-card>

        @elseif ($sshUnavailable)

            <x-ssh.unavailable-alert
                :message="$sshErrorMessage"
                :retry-after="$sshRetryAfter"
                retry-action="retryConnection"
            />

        @elseif ($errorMessage !== null)

            <x-card class="border border-error/20 bg-error/5 py-12 text-center">

                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-error/10"
                >
                    <x-icon
                        name="o-exclamation-triangle"
                        class="size-7 text-error"
                    />
                </div>

                <h3 class="mt-4 text-lg font-semibold text-base-content">
                    دریافت وضعیت برنامه‌ها ناموفق بود
                </h3>

                <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-base-content/60">
                    {{ $errorMessage }}
                </p>

            </x-card>

        @else

            {{-- همان Grid فعلی کارت‌های برنامه‌ها --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">

                @forelse ($applications as $application)

                    <x-applications.card
                        :application="$application"
                    />

                @empty

                    {{-- Empty state فعلی --}}

                @endforelse

            </div>

        @endif

    </div>
</div>
