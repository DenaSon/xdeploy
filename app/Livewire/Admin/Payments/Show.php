<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Payments;

use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('جزئیات پرداخت')]
final class Show extends Component
{
    public Payment $payment;

    public function mount(Payment $payment): void
    {
        $this->payment = $payment;
    }

    public function render(): View
    {
        $payment = $this->payment->load([
            'order.user',
            'order.historicalServer',
        ]);

        return view(
            'livewire.admin.payments.show',
            ['payment' => $payment],
        );
    }
}
