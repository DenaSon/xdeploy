<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Pages;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ویرایش صفحه')]
final class Edit extends Component
{
    #[Locked]
    public int $pageId;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public bool $isPublished = false;

    public bool $showInFooter = false;

    public int $sortOrder = 0;

    public function mount(Page $page): void
    {
        $this->pageId = (int) $page->getKey();
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->content = $page->content ?? '';
        $this->isPublished = $page->is_published;
        $this->showInFooter = $page->show_in_footer;
        $this->sortOrder = $page->sort_order;
    }

    public function save()
    {
        $page = Page::query()->findOrFail($this->pageId);

        $this->slug = Str::lower(trim($this->slug));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('pages', 'slug')->ignore($page->getKey()),
            ],
            'content' => [
                Rule::requiredIf($this->isPublished),
                'nullable',
                'string',
                'max:100000',
            ],
            'isPublished' => ['boolean'],
            'showInFooter' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $wasPublished = $page->is_published;

        $page->update([
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'content' => $validated['content'] !== ''
                ? $validated['content']
                : null,
            'is_published' => $validated['isPublished'],
            'published_at' => $validated['isPublished']
                ? ($wasPublished && $page->published_at !== null
                    ? $page->published_at
                    : now())
                : null,
            'show_in_footer' => $validated['showInFooter'],
            'sort_order' => $validated['sortOrder'],
        ]);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('admin.page.saved', 'تغییرات صفحه ذخیره شد.');
    }

    public function render(): View
    {
        return view(
            'livewire.admin.pages.edit',
            ['page' => Page::query()->findOrFail($this->pageId)],
        );
    }
}
