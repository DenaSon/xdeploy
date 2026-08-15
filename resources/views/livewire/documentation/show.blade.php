<div class="bg-base-200/30">
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
        <div class="grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start">
            <aside class="hidden lg:block">
                <div class="card card-border sticky top-24 bg-base-100">
                    <div class="card-body gap-4 p-4">
                        <a
                            href="{{ route('docs.index') }}"
                            wire:navigate
                            class="group flex items-center gap-3 rounded-xl px-2 py-1.5"
                        >
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <x-icon
                                    name="lucide.book-open-text"
                                    class="!size-4 stroke-[1.8]"
                                />
                            </span>

                            <div class="min-w-0">
                                <div class="text-sm font-semibold">
                                    مستندات
                                </div>

                                <div class="mt-0.5 truncate text-xs text-base-content/40">
                                    {{ config('app.name') }}
                                </div>
                            </div>
                        </a>

                        <div class="divider my-0"></div>

                        <div class="dashboard-scroll max-h-[calc(100vh-10rem)] overflow-y-auto pe-1">
                            <x-documentation.navigation
                                :categories="$categories"
                                :current-article-id="$article->id"
                            />
                        </div>
                    </div>
                </div>
            </aside>

            <main class="min-w-0">
                <div class="collapse collapse-arrow mb-5 border border-base-300 bg-base-100 lg:hidden">
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
                                :current-article-id="$article->id"
                            />
                        </div>
                    </div>
                </div>

                <div class="breadcrumbs mb-4 overflow-hidden text-xs text-base-content/45 sm:text-sm">
                    <ul>
                        <li>
                            <a
                                href="{{ route('docs.index') }}"
                                wire:navigate
                                class="gap-1.5 transition-colors hover:text-primary"
                            >
                                <x-icon
                                    name="lucide.book-open-text"
                                    class="!size-3.5 stroke-[1.7]"
                                />

                                مستندات
                            </a>
                        </li>

                        <li>
                            <span>{{ $article->category->title }}</span>
                        </li>

                        <li>
                            <span class="max-w-56 truncate text-base-content/65 sm:max-w-none">
                                {{ $article->title }}
                            </span>
                        </li>
                    </ul>
                </div>

                <article class="card card-border overflow-hidden bg-base-100">
                    <div class="card-body block p-6 sm:p-8 lg:p-10">
                        <header>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-outline badge-primary gap-1.5">
                                    <x-icon
                                        name="lucide.folder-open"
                                        class="!size-3.5 stroke-[1.8]"
                                    />

                                    {{ $article->category->title }}
                                </span>

                                @if($article->published_at)
                                    <span class="badge badge-ghost gap-1.5">
                                        <x-icon
                                            name="lucide.calendar-days"
                                            class="!size-3.5 stroke-[1.7]"
                                        />

                                        {{ $article->published_at->format('Y-m-d') }}
                                    </span>
                                @endif
                            </div>

                            <h1 class="mt-5 text-2xl font-semibold leading-tight tracking-tight sm:text-3xl lg:text-[2rem]">
                                {{ $article->title }}
                            </h1>

                            @if($article->excerpt)
                                <p class="mt-4 max-w-3xl text-sm leading-7 text-base-content/55 sm:text-base sm:leading-8">
                                    {{ $article->excerpt }}
                                </p>
                            @endif
                        </header>

                        <div class="divider my-7 sm:my-8"></div>

                        <div
                            class="
                                max-w-none text-[15px] leading-8 text-base-content/75

                                [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_a]:decoration-primary/30 [&_a]:underline-offset-4 [&_a]:transition-colors hover:[&_a]:decoration-primary

                                [&_blockquote]:my-6 [&_blockquote]:rounded-e-xl [&_blockquote]:border-r-4 [&_blockquote]:border-primary/35 [&_blockquote]:bg-primary/[0.04] [&_blockquote]:px-5 [&_blockquote]:py-3 [&_blockquote]:text-base-content/65

                                [&_code]:rounded-md [&_code]:bg-base-200 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-[0.82em] [&_code]:text-base-content

                                [&_h1]:mb-4 [&_h1]:mt-10 [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:leading-snug [&_h1]:text-base-content
                                [&_h2]:mb-4 [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:leading-snug [&_h2]:text-base-content
                                [&_h3]:mb-3 [&_h3]:mt-8 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:leading-snug [&_h3]:text-base-content
                                [&_h4]:mb-2 [&_h4]:mt-7 [&_h4]:font-semibold [&_h4]:text-base-content

                                [&_hr]:my-8 [&_hr]:border-base-300

                                [&_img]:my-6 [&_img]:max-w-full [&_img]:rounded-2xl [&_img]:border [&_img]:border-base-300

                                [&_li]:my-1.5
                                [&_ol]:my-5 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pr-6
                                [&_p]:my-4

                                [&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-2xl [&_pre]:border [&_pre]:border-neutral/10 [&_pre]:bg-neutral [&_pre]:p-5 [&_pre]:text-left [&_pre]:text-neutral-content
                                [&_pre]:[direction:ltr]
                                [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-neutral-content

                                [&_strong]:font-semibold [&_strong]:text-base-content

                                [&_table]:my-6 [&_table]:w-full [&_table]:border-collapse [&_table]:text-sm
                                [&_td]:border [&_td]:border-base-300 [&_td]:px-3 [&_td]:py-2.5
                                [&_th]:border [&_th]:border-base-300 [&_th]:bg-base-200 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-start [&_th]:font-semibold [&_th]:text-base-content

                                [&_ul]:my-5 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pr-6
                            "
                        >
                            {!! $renderedContent !!}
                        </div>
                    </div>
                </article>

                @if($previousArticle || $nextArticle)
                    <nav
                        class="mt-5 grid gap-3 sm:grid-cols-2"
                        aria-label="پیمایش بین مستندات"
                    >
                        @if($previousArticle)
                            <a
                                href="{{ $previousArticle['url'] }}"
                                wire:navigate
                                class="card card-border group bg-base-100 transition-colors hover:border-primary/25 hover:bg-primary/[0.025]"
                            >
                                <div class="card-body gap-2 p-4 sm:p-5">
                                    <div class="flex items-center gap-1.5 text-xs text-base-content/40">
                                        <x-icon
                                            name="lucide.arrow-right"
                                            class="!size-3.5 stroke-[1.7]"
                                        />

                                        مستند قبلی
                                    </div>

                                    <div class="font-medium transition-colors group-hover:text-primary">
                                        {{ $previousArticle['title'] }}
                                    </div>

                                    <div class="text-xs text-base-content/40">
                                        {{ $previousArticle['category'] }}
                                    </div>
                                </div>
                            </a>
                        @endif

                        @if($nextArticle)
                            <a
                                href="{{ $nextArticle['url'] }}"
                                wire:navigate
                                @class([
                                    'card card-border group bg-base-100 transition-colors hover:border-primary/25 hover:bg-primary/[0.025]',
                                    'sm:col-start-2' => ! $previousArticle,
                                ])
                            >
                                <div class="card-body gap-2 p-4 text-end sm:p-5">
                                    <div class="flex items-center justify-end gap-1.5 text-xs text-base-content/40">
                                        مستند بعدی

                                        <x-icon
                                            name="lucide.arrow-left"
                                            class="!size-3.5 stroke-[1.7]"
                                        />
                                    </div>

                                    <div class="font-medium transition-colors group-hover:text-primary">
                                        {{ $nextArticle['title'] }}
                                    </div>

                                    <div class="text-xs text-base-content/40">
                                        {{ $nextArticle['category'] }}
                                    </div>
                                </div>
                            </a>
                        @endif
                    </nav>
                @endif
            </main>
        </div>
    </div>
</div>
