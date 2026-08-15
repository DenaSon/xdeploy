<div class="space-y-5">
    @if(session('admin.page.saved'))
        <div
            role="status"
            class="rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success"
        >
            {{ session('admin.page.saved') }}
        </div>
    @endif

    <x-admin.page-header
        :title="$page->title"
        description="ویرایش محتوا، آدرس و وضعیت انتشار این صفحه."
        icon="lucide.file-pen-line"
    >
        @if($page->is_published && $page->published_at?->lte(now()))
            <x-slot:actions>
                <a
                    href="{{ route('pages.show', $page->slug) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-ghost btn-sm"
                >
                    <x-icon name="lucide.external-link" class="!size-4 stroke-[1.7]" />
                    مشاهده صفحه
                </a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('livewire.admin.pages._form')
</div>
