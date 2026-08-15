<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Documentation\Categories;

use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ویرایش دسته مستندات')]
final class Edit extends Component
{
    #[Locked]
    public int $categoryId;

    public string $title = '';

    public string $slug = '';

    public string $description = '';

    public int $sortOrder = 0;

    public bool $isPublished = false;

    public function mount(DocumentationCategory $category): void
    {
        $this->categoryId = (int) $category->getKey();
        $this->title = $category->title;
        $this->slug = $category->slug;
        $this->description = $category->description ?? '';
        $this->sortOrder = $category->sort_order;
        $this->isPublished = $category->is_published;
    }

    public function save()
    {
        $category = DocumentationCategory::query()->findOrFail($this->categoryId);
        $this->slug = Str::lower(trim($this->slug));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('documentation_categories', 'slug')->ignore($category->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:100000'],
            'isPublished' => ['boolean'],
        ]);

        $category->update([
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'description' => $validated['description'] !== ''
                ? trim($validated['description'])
                : null,
            'sort_order' => $validated['sortOrder'],
            'is_published' => $validated['isPublished'],
        ]);

        return redirect()
            ->route('admin.documentation.categories.edit', $category)
            ->with('admin.documentation.saved', 'دسته مستندات ذخیره شد.');
    }

    public function render(): View
    {
        return view(
            'livewire.admin.documentation.categories.edit',
            ['category' => DocumentationCategory::query()->findOrFail($this->categoryId)],
        );
    }
}
