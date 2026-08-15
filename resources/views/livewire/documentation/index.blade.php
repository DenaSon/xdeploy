@php
    $articleCount = $categories->sum(
        fn ($category) => $category->articles->count(),
    );
@endphp

<div class="bg-base-200/30">
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
        <section class="hero overflow-hidden rounded-3xl border border-base-300 bg-base-100">
            <div class="hero-content w-full max-w-none flex-col items-start gap-8 px-6 py-8 sm:px-8 sm:py-10 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="badge badge-outline badge-primary gap-1.5">
                        <x-icon
                            name="lucide.book-open-text"
                            class="!size-3.5 stroke-[1.8]"
                        />

                        راهنمای {{ config('app.name') }}
                    </div>

                    <h1 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                        مستندات {{ config('app.name') }}
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm leading-7 text-base-content/55 sm:text-base sm:leading-8">
                        راهنماهای مرحله‌به‌مرحله برای شروع کار، مدیریت سرورها و استفاده از قابلیت‌های {{ config('app.name') }}.
                    </p>

                    @if($categories->isNotEmpty())
                        <div class="mt-5 flex flex-wrap items-center gap-2">
                            <span class="badge badge-ghost gap-1.5">
                                <x-icon name="lucide.folder-open" class="!size-3.5 stroke-[1.7]" />
                                {{ $categories->count() }} دسته
                            </span>

                            <span class="badge badge-ghost gap-1.5">
                                <x-icon name="lucide.files" class="!size-3.5 stroke-[1.7]" />
                                {{ $articleCount }} راهنما
                            </span>
                        </div>
                    @endif
                </div>

                <div
                    aria-hidden="true"
                    class="flex size-20 shrink-0 items-center justify-center rounded-3xl border border-primary/15 bg-primary/[0.06] text-primary sm:size-24"
                >
                    <x-icon
                        name="lucide.book-marked"
                        class="!size-9 stroke-[1.4] sm:!size-11"
                    />
                </div>
            </div>
        </section>

        @if($categories->isNotEmpty())
            <div class="mt-8 grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start">
                <aside class="hidden lg:block">
                    <div class="card card-border sticky top-24 bg-base-100">
                        <div class="card-body gap-4 p-4">
                            <div class="flex items-center gap-2 px-2">
                                <x-icon
                                    name="lucide.list-tree"
                                    class="!size-4 stroke-[1.7] text-base-content/45"
                                />

                                <h2 class="text-sm font-semibold">
                                    فهرست مستندات
                                </h2>
                            </div>

                            <div class="divider my-0"></div>

                            <div class="dashboard-scroll max-h-[calc(100vh-10rem)] overflow-y-auto pe-1">
                                <x-documentation.navigation
                                    :categories="$categories"
                                />
                            </div>
                        </div>
                    </div>
                </aside>

                <main class="min-w-0">
                    <div class="collapse collapse-arrow border border-base-300 bg-base-100 lg:hidden">
                        <input
                            type="checkbox"
                            aria-label="نمایش فهرست مستندات"
                        >

                        <div class="collapse-title flex items-center gap-2 text-sm font-semibold">
                            <x-icon
                                name="lucide.list-tree"
                                class="!size-4 stroke-[1.7] text-base-content/50"
                            />

                            فهرست مستندات
                        </div>

                        <div class="collapse-content">
                            <div class="pt-2">
                                <x-documentation.navigation
                                    :categories="$categories"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-6 lg:mt-0">
                        @foreach($categories as $category)
                            <section
                                id="docs-category-{{ $category->slug }}"
                                wire:key="docs-category-{{ $category->id }}"
                                class="card card-border scroll-mt-24 overflow-hidden bg-base-100"
                            >
                                <div class="card-body gap-0 p-0">
                                    <header class="flex flex-col gap-3 border-b border-base-300/70 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <x-icon
                                                    name="lucide.folder-open"
                                                    class="!size-4 stroke-[1.8]"
                                                />
                                            </span>

                                            <div class="min-w-0">
                                                <h2 class="card-title text-base font-semibold sm:text-lg">
                                                    {{ $category->title }}
                                                </h2>

                                                @if($category->description)
                                                    <p class="mt-1 text-sm leading-7 text-base-content/50">
                                                        {{ $category->description }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>

                                        <span class="badge badge-ghost shrink-0">
                                            {{ $category->articles->count() }} راهنما
                                        </span>
                                    </header>

                                    <div class="list">
                                        @foreach($category->articles as $article)
                                            <a
                                                href="{{ route('docs.show', [$category->slug, $article->slug]) }}"
                                                wire:navigate
                                                wire:key="docs-article-{{ $article->id }}"
                                                class="list-row group rounded-none border-b border-base-300/60 px-5 py-4 transition-colors last:border-b-0 hover:bg-base-200/45 sm:px-6"
                                            >
                                                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200 text-base-content/45 transition-colors group-hover:bg-primary/10 group-hover:text-primary">
                                                    <x-icon
                                                        name="lucide.file-text"
                                                        class="!size-4 stroke-[1.7]"
                                                    />
                                                </div>

                                                <div class="min-w-0">
                                                    <h3 class="font-medium text-base-content transition-colors group-hover:text-primary">
                                                        {{ $article->title }}
                                                    </h3>

                                                    @if($article->excerpt)
                                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-base-content/45">
                                                            {{ $article->excerpt }}
                                                        </p>
                                                    @endif
                                                </div>

                                                <div class="flex items-center self-center text-base-content/30 transition-all group-hover:-translate-x-0.5 group-hover:text-primary">
                                                    <x-icon
                                                        name="lucide.arrow-left"
                                                        class="!size-4 stroke-[1.8]"
                                                    />
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </main>
            </div>
        @else
            <section class="card card-border mt-8 bg-base-100">
                <div class="card-body items-center px-5 py-14 text-center">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-base-200 text-base-content/30">
                        <x-icon
                            name="lucide.book-dashed"
                            class="!size-6 stroke-[1.6]"
                        />
                    </span>

                    <h2 class="mt-2 text-base font-semibold">
                        هنوز مستندی منتشر نشده است
                    </h2>

                    <p class="max-w-md text-sm leading-7 text-base-content/45">
                        راهنماهای {{ config('app.name') }} پس از انتشار در این بخش نمایش داده می‌شوند.
                    </p>
                </div>
            </section>
        @endif
    </div>
</div>
