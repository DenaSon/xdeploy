<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('صفحه')]
final class Show extends Component
{
    #[Locked]
    public int $pageId;

    public function mount(string $slug): void
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->pageId = (int) $page->getKey();
    }

    public function render(): View
    {
        $page = Page::query()
            ->published()
            ->findOrFail($this->pageId);

        return view(
            'livewire.pages.show',
            [
                'page' => $page,
                'renderedContent' => Str::markdown(
                    $page->content ?? '',
                    [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ],
                ),
            ],
        );
    }
}
