@props([
    'title',
    'description' => null,
    'icon' => 'lucide.circle',
])

<section
    {{ $attributes->class([
        'overflow-hidden rounded-2xl',
        'border border-base-300 bg-base-100',
    ]) }}
>
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="flex min-w-0 items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <x-icon :name="$icon" class="!size-5 stroke-[1.8]" />
            </div>

            <div class="min-w-0">
                <h1 class="text-lg font-semibold tracking-tight sm:text-xl">
                    {{ $title }}
                </h1>

                @if($description)
                    <p class="mt-1.5 max-w-3xl text-sm leading-7 text-base-content/55">
                        {{ $description }}
                    </p>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="shrink-0">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
