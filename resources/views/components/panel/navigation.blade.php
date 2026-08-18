@php
    use App\Application\Navigation\PublicDocumentationNavigation;
    use App\Models\Server;

    $routeServer = request()->route('server');

    $contextServer = $routeServer instanceof Server
        ? $routeServer
        : null;

    $documentationCategories = app(
        PublicDocumentationNavigation::class,
    )->categories();
@endphp

<nav class="flex-1 px-2 py-4">

    <x-menu>

        <x-menu-item
            title="سرورها"
            icon="lucide.server"
            :link="route('panel.servers.index')"
            :active="request()->routeIs('panel.servers.*')
                && ! request()->routeIs('panel.servers.domains.*')"
            wire:navigate

            class="
                rounded-xl

                text-sm
                text-base-content/65

                transition-colors duration-200

                hover:bg-base-200
                hover:text-base-content
            "

            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "

            icon-classes="
                !size-[18px]
                stroke-[1.7]
            "
        />

        @if ($contextServer !== null)
            <x-menu-item
                title="دامنه‌ها"
                icon="lucide.globe-2"
                :link="route(
                    'panel.servers.domains.index',
                    ['server' => $contextServer],
                )"
                :active="request()->routeIs('panel.servers.domains.*')"
                wire:navigate

                class="
                    rounded-xl

                    text-sm
                    text-base-content/65

                    transition-colors duration-200

                    hover:bg-base-200
                    hover:text-base-content
                "

                active-bg-color="
                    !bg-primary/10
                    !text-primary
                    !font-medium
                "

                icon-classes="
                    !size-[18px]
                    stroke-[1.7]
                "
            />
        @endif

        <x-menu-item
            title="یکپارچه‌سازی‌ها"
            icon="lucide.link-2"
            :link="route('panel.integrations.index')"
            :active="request()->routeIs('panel.integrations.*')"
            wire:navigate

            class="
                rounded-xl
                text-sm
                text-base-content/65
                transition-colors duration-200
                hover:bg-base-200
                hover:text-base-content
            "

            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "

            icon-classes="
                !size-[18px]
                stroke-[1.7]
            "
        />

        <x-menu-item
            title="پشتیبانی"
            icon="lucide.headset"
            :link="route('panel.support.index')"
            :active="request()->routeIs('panel.support.*')"
            wire:navigate

            class="
                rounded-xl
                text-sm
                text-base-content/65
                transition-colors duration-200
                hover:bg-base-200
                hover:text-base-content
            "

            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "

            icon-classes="
                !size-[18px]
                stroke-[1.7]
            "
        />

        <x-menu-sub
            title="راهنما"
            icon="lucide.book-open-text"
            class="text-sm text-base-content/65"
            icon-classes="!size-[18px] stroke-[1.7]"
        >
            <x-menu-item
                title="همه آموزش‌ها"
                icon="lucide.library-big"
                :link="route('docs.index')"
                wire:navigate

                class="
                    rounded-xl
                    text-sm
                    text-base-content/60
                    transition-colors duration-200
                    hover:bg-base-200
                    hover:text-base-content
                "

                active-bg-color="
                    !bg-primary/10
                    !text-primary
                    !font-medium
                "

                icon-classes="
                    !size-4
                    stroke-[1.7]
                "
            />

            @foreach($documentationCategories as $category)
                <x-menu-item
                    :title="$category['title']"
                    icon="lucide.folder-open"
                    :link="route('docs.index').'#docs-category-'.$category['slug']"
                    no-wire-navigate
                    wire:key="panel-guide-category-{{ $category['slug'] }}"

                    class="
                        rounded-xl
                        text-sm
                        text-base-content/55
                        transition-colors duration-200
                        hover:bg-base-200
                        hover:text-base-content
                    "

                    active-bg-color="
                        !bg-primary/10
                        !text-primary
                        !font-medium
                    "

                    icon-classes="
                        !size-4
                        stroke-[1.7]
                    "
                />
            @endforeach
        </x-menu-sub>

        <x-menu-item
            title="امنیت حساب"
            icon="lucide.shield-check"
            :link="route('panel.security')"
            :active="request()->routeIs('panel.security*')"
            wire:navigate

            class="
                rounded-xl
                text-sm
                text-base-content/65
                transition-colors duration-200
                hover:bg-base-200
                hover:text-base-content
            "

            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "

            icon-classes="
                !size-[18px]
                stroke-[1.7]
            "
        />

    </x-menu>

</nav>
