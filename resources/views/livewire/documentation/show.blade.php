<div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
    <nav class="mb-6 flex flex-wrap items-center gap-2 text-xs text-base-content/45" aria-label="مسیر مستندات">
        <a href="{{ route('docs.index') }}" wire:navigate class="transition hover:text-primary">مستندات</a>
        <x-icon name="lucide.chevron-left" class="!size-3.5 stroke-[1.7]" />
        <span>{{ $article->category->title }}</span>
        <x-icon name="lucide.chevron-left" class="!size-3.5 stroke-[1.7]" />
        <span class="text-base-content/70">{{ $article->title }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_15rem]">
        <main class="min-w-0">
            <header class="border-b border-base-300 pb-6 sm:pb-8">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <x-icon name="lucide.file-text" class="!size-5 stroke-[1.8]" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-xs font-medium text-primary">{{ $article->category->title }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $article->title }}</h1>

                        @if($article->excerpt)
                            <p class="mt-3 text-sm leading-7 text-base-content/55">{{ $article->excerpt }}</p>
                        @endif
                    </div>
                </div>
            </header>

            <article
                class="
                    mt-8 text-[15px] leading-8 text-base-content/75
                    [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4
                    [&_blockquote]:my-5 [&_blockquote]:border-r-2 [&_blockquote]:border-primary/30 [&_blockquote]:pr-4 [&_blockquote]:text-base-content/60
                    [&_code]:rounded [&_code]:bg-base-200 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs
                    [&_h2]:mb-3 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-base-content
                    [&_h3]:mb-2 [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-base-content
                    [&_li]:my-1.5
                    [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pr-6
                    [&_p]:my-4
                    [&_pre]:my-5 [&_pre]:overflow-x-auto [&_pre]:rounded-xl [&_pre]:bg-base-200 [&_pre]:p-4
                    [&_pre_code]:bg-transparent [&_pre_code]:p-0
                    [&_strong]:font-semibold [&_strong]:text-base-content
                    [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pr-6
                "
            >
                {!! $renderedContent !!}
            </article>
        </main>

        <aside class="hidden lg:block">
            <div class="sticky top-24 rounded-2xl border border-base-300 bg-base-100 p-4">
                <div class="text-xs font-medium text-base-content/45">دسته</div>
                <div class="mt-1 text-sm font-medium">{{ $article->category->title }}</div>

                <div class="mt-5 border-t border-base-300 pt-4 text-xs text-base-content/40">
                    @if($article->published_at)
                        انتشار: {{ $article->published_at->format('Y-m-d') }}
                    @endif
                </div>

                <a href="{{ route('docs.index') }}" wire:navigate class="btn btn-ghost btn-sm mt-4 w-full">
                    بازگشت به مستندات
                </a>
            </div>
        </aside>
    </div>
</div>
