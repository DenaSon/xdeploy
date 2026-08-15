<nav class="flex-1 px-2 py-4">
    <x-menu>
        @php
            $items = [
                ['title' => 'داشبورد', 'icon' => 'lucide.layout-dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                ['title' => 'کاربران', 'icon' => 'lucide.users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                ['title' => 'سرورها', 'icon' => 'lucide.server', 'route' => 'admin.servers.index', 'active' => 'admin.servers.*'],
                ['title' => 'سفارش‌ها', 'icon' => 'lucide.receipt-text', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*'],
                ['title' => 'پرداخت‌ها', 'icon' => 'lucide.credit-card', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*'],
                ['title' => 'پشتیبانی', 'icon' => 'lucide.headset', 'route' => 'admin.support.index', 'active' => 'admin.support.*'],
                ['title' => 'مستندات', 'icon' => 'lucide.book-open-text', 'route' => 'admin.documentation.articles.index', 'active' => 'admin.documentation.*'],
                ['title' => 'صفحات', 'icon' => 'lucide.files', 'route' => 'admin.pages.index', 'active' => 'admin.pages.*'],
            ];
        @endphp

        @foreach($items as $item)
            <x-menu-item
                :title="$item['title']"
                :icon="$item['icon']"
                :link="route($item['route'])"
                :active="request()->routeIs($item['active'])"
                wire:navigate
                class="rounded-xl text-sm text-base-content/65 transition-colors duration-200 hover:bg-base-200 hover:text-base-content"
                active-bg-color="!bg-primary/10 !text-primary !font-medium"
                icon-classes="!size-[18px] stroke-[1.7]"
            />
        @endforeach
    </x-menu>
</nav>
