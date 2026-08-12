@props([
    'link',
])

<div
    {{ $attributes->class([
        'card group relative overflow-hidden',
        'border border-base-300/80',
        'bg-base-100',
        'shadow-sm',
        'transition-all duration-200',
        'hover:border-success/30',
        'hover:shadow-md',
    ]) }}
>
    {{-- Soft accent --}}
    <div
        class="
            pointer-events-none absolute
            -start-16 -top-20
            size-40 rounded-full
            bg-success/[0.07]
            blur-3xl
        "
    ></div>

    <div class="card-body relative p-5 sm:p-6">

        <div class="flex items-start gap-3.5">

            <div
                class="
                    flex size-10 shrink-0
                    items-center justify-center
                    rounded-xl
                    border border-success/15
                    bg-success/[0.08]
                    text-success
                "
            >
                <x-icon
                    name="lucide.cloud"
                    class="!size-[18px] stroke-[1.8]"
                />
            </div>

            <div class="min-w-0 flex-1">

                <h2
                    class="
                        text-[15px] font-semibold
                        text-base-content
                        sm:text-base
                    "
                >
                    هنوز VPS نداری؟
                </h2>

                <p
                    class="
                        mt-1.5
                        max-w-xl
                        text-sm leading-6
                        text-base-content/55
                    "
                >
                    یک سرور متناسب با نیازت انتخاب کن؛
                    پس از آماده‌سازی، به‌سادگی آن را به xDeploy اضافه کن.
                </p>

            </div>

        </div>

        <div
            class="
                mt-5 flex
                items-center justify-end
                border-t border-base-300/60
                pt-4
            "
        >
            <x-button
                label="مشاهده سرورها"
                icon="lucide.arrow-left"
                :link="$link"
                wire:navigate
                class="
                    btn-success btn-sm
                    rounded-lg
                    px-4
                    font-medium
                "
            />
        </div>

    </div>
</div>
