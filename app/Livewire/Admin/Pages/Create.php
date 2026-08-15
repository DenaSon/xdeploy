<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Pages;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ایجاد صفحه')]
final class Create extends Component
{
    public string $title = '';

    public string $slug = '';

    public string $content = '';

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
                'unique:pages,slug',
            ],
            'content' => [
                Rule::requiredIf($this->isPublished),
                'nullable',
                'string',
                'max:100000',
            ],
            'isPublished' => ['boolean'],
        ]);

        $page = Page::query()->create([
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'content' => $validated['content'] !== ''
                ? $validated['content']
                : null,
            'is_published' => $validated['isPublished'],
            'published_at' => $validated['isPublished']
                ? now()
                : null,
        ]);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('admin.page.saved', 'صفحه ایجاد شد.');
    }

    public function render(): View
    {
        return view('livewire.admin.pages.create');
    }
}
