<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Analytics;

use App\Application\Analytics\Contracts\ProductAnalyticsReporting;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('تحلیل محصول')]
final class Dashboard extends Component
{
    public int $days = 7;

    public function setRange(int $days): void
    {
        if (! in_array($days, [7, 30, 90], true)) {
            return;
        }

        $this->days = $days;
    }

    public function render(): View
    {
        /** @var ProductAnalyticsReporting $reporting */
        $reporting = app(ProductAnalyticsReporting::class);
        $report = $reporting->report($this->days);

        return view(
            'livewire.admin.analytics.dashboard',
            [
                'report' => $report->toArray(),
                'funnelSections' => [
                    'purchase' => [
                        'title' => 'Purchase Funnel',
                        'description' => 'از ورود تا پرداخت موفق',
                        'icon' => 'lucide.shopping-cart',
                    ],
                    'fulfillment' => [
                        'title' => 'Fulfillment Funnel',
                        'description' => 'از پرداخت تا آماده‌شدن VPS',
                        'icon' => 'lucide.server-cog',
                    ],
                    'activation' => [
                        'title' => 'Activation Funnel',
                        'description' => 'از VPS آماده تا برنامه در حال اجرا',
                        'icon' => 'lucide.rocket',
                    ],
                ],
            ],
        );
    }
}