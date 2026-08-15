<div class="space-y-5">
    <x-admin.page-header
        title="مستندات"
        description="مدیریت مقاله‌های راهنما، ترتیب نمایش و وضعیت انتشار مستندات عمومی."
        icon="lucide.book-open-text"
    >
        <x-slot:actions>
            <x-button
                label="دسته‌ها"
                icon="lucide.folder-tree"
                :link="route('admin.documentation.categories.index')"
                wire:navigate
                class="btn-ghost btn-sm"
            />
            <x-button
                label="مقاله جدید"
                icon="lucide.plus"
                :link="route('admin.documentation.articles.create')"
                wire:navigate
                class="btn-primary btn-sm"
            />
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-4 border-b border-base-300 p-4 md:grid-cols-[minmax(0,1fr)_12rem_14rem] sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="عنوان، slug یا دسته"
                icon="lucide.search"
                wire:model.live.debounce.300ms="search"
                clearable
            />

            <label class="form-control">
                <span class="mb-2 text-sm font-medium">وضعیت</span>
                <select wire:model.live="status" class="select select-bordered w-full">
                    <option value="all">همه</option>
                    <option value="published">منتشرشده</option>
                    <option value="draft">پیش‌نویس</option>
                </select>
            </label>

            <label class="form-control">
                <span class="mb-2 text-sm font-medium">دسته</span>
                <select wire:model.live="category" class="select select-bordered w-full">
                    <option value="all">همه دسته‌ها</option>
                    @foreach($categories as $categoryItem)
                        <option value="{{ $categoryItem->id }}">{{ $categoryItem->title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>دسته</th>
                        <th>Slug</th>
                        <th>وضعیت</th>
                        <th>ترتیب</th>
                        <th>انتشار</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($articles as $article)
                        <tr wire:key="documentation-article-{{ $article->id }}">
                            <td class="font-medium">{{ $article->title }}</td>
                            <td class="text-sm text-base-content/60">{{ $article->category->title }}</td>
                            <td><code class="text-xs text-base-content/60" dir="ltr">{{ $article->slug }}</code></td>
                            <td>
                                <x-admin.status-badge :status="$article->is_published ? 'published' : 'draft'" />
                            </td>
                            <td class="text-sm text-base-content/55">{{ $article->sort_order }}</td>
                            <td class="text-sm text-base-content/55">{{ $article->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-left">
                                <div class="flex items-center justify-end gap-1">
                                    @if($article->is_published && $article->published_at?->lte(now()) && $article->category->is_published)
                                        <a
                                            href="{{ route('docs.show', [$article->category->slug, $article->slug]) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-ghost btn-sm btn-square"
                                            aria-label="مشاهده مقاله عمومی"
                                        >
                                            <x-icon name="lucide.external-link" class="!size-4 stroke-[1.7]" />
                                        </a>
                                    @endif

                                    <x-button
                                        label="ویرایش"
                                        icon="lucide.pencil"
                                        :link="route('admin.documentation.articles.edit', $article)"
                                        wire:navigate
                                        class="btn-ghost btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-base-content/45">
                                مقاله‌ای پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($articles->hasPages())
            <div class="border-t border-base-300 p-4">
                {{ $articles->links() }}
            </div>
        @endif
    </section>
</div>
