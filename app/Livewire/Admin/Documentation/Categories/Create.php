<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Documentation\Categories;

use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ایجاد دسته مستندات')]
final class Create extends Component
{
    public string $title = '';

    public string $slug = '';

    public string $description = '';

    public int $sortOrder = 0;

    public bool $isPublished = false;

    public function save()
    {
        $this->slug = Str::lower(trim($this->slug));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                'unique:documentation_categories,slug',
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:100000'],
            'isPublished' => ['boolean'],
        ]);

        $category = DocumentationCategory::query()->create([
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
            ->with('admin.documentation.saved', 'دسته مستندات ایجاد شد.');
    }

    public function render(): View
    {
        return view('livewire.admin.documentation.categories.create');
    }
}
