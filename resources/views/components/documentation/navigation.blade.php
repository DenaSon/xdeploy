@props([
    'categories',
    'currentArticleId' => null,
    'showOverview' => true,
])

<nav aria-label="فهرست مستندات">
    <ul class="menu menu-sm w-full gap-0.5 p-0">
        @if($showOverview)
            @php
                $isOverview = request()->routeIs('docs.index');
            @endphp

            <li>
                <a
                    href="{{ route('docs.index') }}"
                    wire:navigate
                    @if($isOverview) aria-current="page" @endif
                    @class([
                        'rounded-xl text-base-content/60 transition-colors',
                        'bg-primary/10 font-medium !text-primary ring-1 ring-inset ring-primary/15' => $isOverview,
                    ])
                >
                    <x-icon
                        name="lucide.layout-grid"
                        @class([
                            '!size-4 stroke-[1.7]',
                            'text-primary' => $isOverview,
                        ])
                    />

                    <span>همه مستندات</span>
                </a>
            </li>
        @endif

        @foreach($categories as $category)
            <li class="menu-title mt-4 px-2 first:mt-2">
                <span class="flex w-full items-center justify-between gap-2">
                    <span class="truncate">{{ $category->title }}</span>

                    <span class="badge badge-ghost badge-xs shrink-0">
                        {{ $category->articles->count() }}
                    </span>
                </span>
            </li>

            @foreach($category->articles as $navigationArticle)
                @php
                    $isCurrent = (int) $currentArticleId === (int) $navigationArticle->getKey();
                @endphp

                <li wire:key="docs-navigation-article-{{ $navigationArticle->id }}">
                    <a
                        href="{{ route('docs.show', [$category->slug, $navigationArticle->slug]) }}"
                        wire:navigate
                        @if($isCurrent) aria-current="page" @endif
                        @class([
                            'min-w-0 rounded-xl text-base-content/60 transition-colors',
                            'bg-primary/10 font-medium !text-primary ring-1 ring-inset ring-primary/15' => $isCurrent,
                        ])
                    >
                        <x-icon
                            name="lucide.file-text"
                            @class([
                                '!size-3.5 shrink-0 stroke-[1.7]',
                                'opacity-65' => ! $isCurrent,
                                'text-primary' => $isCurrent,
                            ])
                        />

                        <span class="truncate">
                            {{ $navigationArticle->title }}
                        </span>
                    </a>
                </li>
            @endforeach
        @endforeach
    </ul>
</nav>
