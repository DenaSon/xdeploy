<div class="space-y-5">
    @if(session('admin.documentation.saved'))
        <div role="status" class="rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
            {{ session('admin.documentation.saved') }}
        </div>
    @endif

    <x-admin.page-header
        :title="$article->title"
        description="ویرایش محتوا، دسته، ترتیب و وضعیت انتشار این مقاله."
        icon="lucide.file-pen-line"
    >
        @if($article->is_published && $article->published_at?->lte(now()) && $article->category->is_published)
            <x-slot:actions>
                <a
                    href="{{ route('docs.show', [$article->category->slug, $article->slug]) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-ghost btn-sm"
                >
                    <x-icon name="lucide.external-link" class="!size-4 stroke-[1.7]" />
                    مشاهده مقاله
                </a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @include('livewire.admin.documentation.articles._form')
</div>
