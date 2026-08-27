<nav class="flex-1 px-2 py-4">
    <x-menu>
        @php
            $items = [
                ['title' => 'داشبورد', 'icon' => 'lucide.layout-dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                ['title' => 'کاربران', 'icon' => 'lucide.users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                ['title' => 'سرورها', 'icon' => 'lucide.server', 'route' => 'admin.servers.index', 'active' => 'admin.servers.*'],
                ['title' => 'ارائه‌دهندگان ابری', 'icon' => 'lucide.cloud', 'route' => 'admin.cloud-providers.index', 'active' => 'admin.cloud-providers.*'],
                ['title' => 'بررسی Volumeها', 'icon' => 'lucide.hard-drive', 'route' => 'admin.cloud-volumes.index', 'active' => 'admin.cloud-volumes.*'],
                ['title' => 'سفارش‌ها', 'icon' => 'lucide.receipt-text', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*'],
                ['title' => 'پرداخت‌ها', 'icon' => 'lucide.credit-card', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*'],
                ['title' => 'پشتیبانی', 'icon' => 'lucide.headset', 'route' => 'admin.support.index', 'active' => 'admin.support.*'],
                ['title' => 'مستندات', 'icon' => 'lucide.book-open-text', 'route' => 'admin.documentation.articles.index', 'active' => 'admin.documentation.*'],
                ['title' => 'صفحات', 'icon' => 'lucide.files', 'route' => 'admin.pages.index', 'active' => 'admin.pages.*'],
                ['title' => 'تنظیمات سامانه', 'icon' => 'lucide.settings-2', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*'],
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

        @if(
            config('log-viewer.enabled')
            && \Illuminate\Support\Facades\Route::has('log-viewer.index')
        )
            <x-menu-item
                title="لاگ‌ها"
                icon="lucide.file-search"
                :link="route('log-viewer.index')"
                :active="request()->routeIs('log-viewer.*')"
                class="rounded-xl text-sm text-base-content/65 transition-colors duration-200 hover:bg-base-200 hover:text-base-content"
                active-bg-color="!bg-primary/10 !text-primary !font-medium"
                icon-classes="!size-[18px] stroke-[1.7]"
            />
        @endif
    </x-menu>
</nav>
