<div class="space-y-5">
    <x-admin.page-header
        title="صفحات"
        description="مدیریت صفحات ثابت عمومی مانند قوانین، حریم خصوصی، درباره ما و تماس با ما."
        icon="lucide.files"
    >
        <x-slot:actions>
            <x-button
                label="صفحه جدید"
                icon="lucide.plus"
                :link="route('admin.pages.create')"
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
                <select
                    wire:model.live="status"
                    class="select select-bordered w-full"
                >
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
                        <th>وضعیت</th>
                        <th>انتشار</th>
                        <th>آخرین ویرایش</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($pages as $page)
                        <tr wire:key="admin-page-{{ $page->id }}">
                            <td class="font-medium">{{ $page->title }}</td>
                            <td>
                                <code class="text-xs text-base-content/60" dir="ltr">{{ $page->slug }}</code>
                            </td>
                            <td>
                                <x-admin.status-badge :status="$page->is_published ? 'published' : 'draft'" />
                            </td>
                            <td class="text-sm text-base-content/55">
                                {{ $page->published_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="text-sm text-base-content/55">
                                {{ $page->updated_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="text-left">
                                <div class="flex items-center justify-end gap-1">
                                    @if($page->is_published && $page->published_at?->lte(now()))
                                        <a
                                            href="{{ route('pages.show', $page->slug) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-ghost btn-sm btn-square"
                                            aria-label="مشاهده صفحه عمومی"
                                        >
                                            <x-icon name="lucide.external-link" class="!size-4 stroke-[1.7]" />
                                        </a>
                                    @endif

                                    <x-button
                                        label="ویرایش"
                                        icon="lucide.pencil"
                                        :link="route('admin.pages.edit', $page)"
                                        wire:navigate
                                        class="btn-ghost btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-base-content/45">
                                صفحه‌ای پیدا نشد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pages->hasPages())
            <div class="border-t border-base-300 p-4">
                {{ $pages->links() }}
            </div>
        @endif
    </section>
</div>
