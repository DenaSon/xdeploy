<?php

declare(strict_types=1);

namespace App\Livewire\Documentation;

use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('مستندات')]
final class Index extends Component
{
    public function render(): View
    {
        $categories = DocumentationCategory::query()
            ->published()
            ->whereHas(
                'articles',
                fn ($query) => $query->published(),
            )
            ->with([
                'articles' => fn ($query) => $query
                    ->published()
                    ->ordered(),
            ])
            ->ordered()
            ->get();

        return view(
            'livewire.documentation.index',
            ['categories' => $categories],
        );
    }
}
