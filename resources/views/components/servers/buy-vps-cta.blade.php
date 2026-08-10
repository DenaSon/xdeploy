@props([
    'link',
])

<div
    {{ $attributes->class([
        'card card-dash',
        'border-success/25',
        'bg-success/[0.04]',
    ]) }}
>
    <div class="card-body p-5 sm:p-6">

        <div class="flex items-start gap-3">

            <span
                class="
                    flex size-9 shrink-0
                    items-center justify-center

                    rounded-xl
                    bg-success/10
                    text-success
                "
            >
                <x-icon
                    name="lucide.cloud"
                    class="!size-4 stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">

                <h2 class="card-title text-base font-semibold">
                    VPS در اختیار نداری؟
                </h2>

                <p
                    class="
                        mt-1
                        text-sm leading-7
                        text-base-content/50
                    "
                >
                    می‌توانی یک سرور جدید انتخاب کنی و پس از آماده‌شدن،
                    آن را مستقیماً به xDeploy اضافه کنی.
                </p>

            </div>

        </div>


        <div class="card-actions mt-4 justify-end">

            <x-button
                label="انتخاب VPS"
                icon="lucide.arrow-left"
                :link="$link"
                wire:navigate
                class="
                    btn-success btn-sm
                    rounded-xl
                    font-medium
                "
            />

        </div>

    </div>
</div>
