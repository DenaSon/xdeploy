<div class="space-y-5">
    <x-admin.page-header
        title="دسته‌بندی مستندات"
        description="ساختار بخش مستندات را مدیریت و ترتیب نمایش دسته‌ها را مشخص کنید."
        icon="lucide.folder-tree"
    >
        <x-slot:actions>
            <x-button
                label="مقاله‌ها"
                icon="lucide.book-open-text"
                :link="route('admin.documentation.articles.index')"
                wire:navigate
                class="btn-ghost btn-sm"
            />
            <x-button
                label="دسته جدید"
                icon="lucide.plus"
                :link="route('admin.documentation.categories.create')"
                wire:navigate
                class="btn-primary btn-sm"
            />
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div class="grid gap-4 border-b border-base-300 p-4 sm:grid-cols-[minmax(0,1fr)_12rem] sm:p-5">
            <x-input
                label="جست‌وجو"
                placeholder="عنوان یا slug"
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
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>عنوان</th>
                        <th>Slug</th>
                        <th>مقاله‌ها</th>
                        <th>ترتیب</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr wire:key="documentation-category-{{ $category->id }}">
                            <td class="font-medium">{{ $category->title }}</td>
                            <td><code class="text-xs text-base-content/60" dir="ltr">{{ $category->slug }}</code></td>
                            <td class="text-sm text-base-content/55">{{ $category->articles_count }}</td>
                            <td class="text-sm text-base-content/55">{{ $category->sort_order }}</td>
                            <td>
                                <x-admin.status-badge :status="$category->is_published ? 'published' : 'draft'" />
                            </td>
                            <td class="text-left">
                                <x-button
                                    label="ویرایش"
                                    icon="lucide.pencil"
                                    :link="route('admin.documentation.categories.edit', $category)"
                                    wire:navigate
                                    class="btn-ghost btn-sm"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-base-content/45">
                                دسته‌ای پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($categories->hasPages())
            <div class="border-t border-base-300 p-4">
                {{ $categories->links() }}
            </div>
        @endif
    </section>
</div>
