<div class="space-y-5">
    @if(session('admin.documentation.saved'))
        <div role="status" class="rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
            {{ session('admin.documentation.saved') }}
        </div>
    @endif

    <x-admin.page-header
        :title="$category->title"
        description="ویرایش مشخصات، ترتیب و وضعیت انتشار این دسته."
        icon="lucide.folder-pen"
    >
        @if($category->is_published)
            <x-slot:actions>
                <a
                    href="{{ route('docs.index') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-ghost btn-sm"
                >
                    <x-icon name="lucide.external-link" class="!size-4 stroke-[1.7]" />
                    مشاهده مستندات
                </a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('livewire.admin.documentation.categories._form')
</div>
