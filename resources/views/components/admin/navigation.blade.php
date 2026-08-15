<nav class="flex-1 px-2 py-4">
    <x-menu>
        <x-menu-item
            title="داشبورد"
            icon="lucide.layout-dashboard"
            :link="route('admin.dashboard')"
            :active="request()->routeIs('admin.dashboard')"
            wire:navigate
            class="
                rounded-xl
                text-sm text-base-content/65
                transition-colors duration-200
                hover:bg-base-200
                hover:text-base-content
            "
            active-bg-color="
                !bg-primary/10
                !text-primary
                !font-medium
            "
            icon-classes="!size-[18px] stroke-[1.7]"
        />
    </x-menu>
</nav>
