<div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
    <header class="max-w-3xl">
        <div class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <x-icon name="lucide.book-open-text" class="!size-5 stroke-[1.8]" />
        </div>

        <h1 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
            مستندات {{ config('app.name') }}
        </h1>

        <p class="mt-3 text-sm leading-7 text-base-content/55 sm:text-base">
            راهنماهای استفاده از سرویس، مدیریت سرورها و قابلیت‌های {{ config('app.name') }}.
        </p>
    </header>

    <div class="mt-10 space-y-8">
        @forelse($categories as $category)
            <section wire:key="docs-category-{{ $category->id }}">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold sm:text-xl">{{ $category->title }}</h2>
                    @if($category->description)
                        <p class="mt-1 text-sm leading-7 text-base-content/50">{{ $category->description }}</p>
                    @endif
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    @foreach($category->articles as $article)
                        <a
                            href="{{ route('docs.show', [$category->slug, $article->slug]) }}"
                            wire:navigate
                            class="group rounded-2xl border border-base-300 bg-base-100 p-5 transition hover:border-primary/25 hover:bg-base-200/30"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="font-medium transition-colors group-hover:text-primary">
                                        {{ $article->title }}
                                    </h3>

                                    @if($article->excerpt)
                                        <p class="mt-2 line-clamp-2 text-sm leading-7 text-base-content/50">
                                            {{ $article->excerpt }}
                                        </p>
                                    @endif
                                </div>

                                <x-icon
                                    name="lucide.arrow-left"
                                    class="mt-1 !size-4 shrink-0 stroke-[1.7] text-base-content/35 transition group-hover:text-primary"
                                />
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="rounded-2xl border border-dashed border-base-300 bg-base-100 px-5 py-12 text-center">
                <x-icon name="lucide.book-dashed" class="mx-auto !size-8 stroke-[1.5] text-base-content/30" />
                <p class="mt-3 text-sm text-base-content/45">هنوز مستندی منتشر نشده است.</p>
            </section>
        @endforelse
    </div>
</div>
