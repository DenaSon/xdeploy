<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Support;

use App\Application\Support\Actions\CloseSupportRequestAction;
use App\Application\Support\Actions\ReplyToSupportRequestAsAdminAction;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('درخواست پشتیبانی')]
final class Show extends Component
{
    public int $supportRequestId;

    public string $reply = '';

    public ?string $statusMessage = null;

    public function mount(string $supportRequestId): void
    {
        abort_unless(ctype_digit($supportRequestId), 404);

        $this->supportRequestId = (int) $supportRequestId;

        $this->supportRequest();
    }

    public function sendReply(
        ReplyToSupportRequestAsAdminAction $replyToSupportRequest,
    ): void {
        $validated = $this->validate([
            'reply' => ['required', 'string', 'max:10000'],
        ]);

        $replyToSupportRequest->execute(
            admin: $this->admin(),
            supportRequestId: $this->supportRequestId,
            message: (string) $validated['reply'],
        );

        $this->reset('reply');
        $this->resetValidation();
        $this->statusMessage = 'پاسخ مدیریت ثبت و برای کاربر اعلان شد.';

        $this->dispatch('support-message-sent');
    }

    public function close(
        CloseSupportRequestAction $closeSupportRequest,
    ): void {
        $closeSupportRequest->execute(
            actor: $this->admin(),
            supportRequestId: $this->supportRequestId,
        );

        $this->resetValidation();
        $this->statusMessage = 'درخواست پشتیبانی بسته شد.';
    }

    public function render(): View
    {
        return view(
            'livewire.admin.support.show',
            [
                'supportRequest' => $this->supportRequest(
                    withRelations: true,
                ),
            ],
        );
    }

    private function supportRequest(
        bool $withRelations = false,
    ): SupportRequest {
        $query = SupportRequest::query()
            ->whereKey($this->supportRequestId);

        if ($withRelations) {
            $query->with([
                'user.profile',
                'server',
                'messages.author.profile',
            ]);
        }

        /** @var SupportRequest $supportRequest */
        $supportRequest = $query->firstOrFail();

        return $supportRequest;
    }

    private function admin(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && $user->isAdmin(),
            403,
        );

        return $user;
    }
}
