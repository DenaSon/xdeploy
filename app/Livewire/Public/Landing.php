<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Support\Seo\PublicSeo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class Landing extends Component
{
    public function render(): View
    {
        return view('livewire.public.landing')
            ->layout('layouts.public', [
                'seo' => app(PublicSeo::class)->landing(),
            ]);
    }
}
