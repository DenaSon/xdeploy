<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Application\Support\Actions\CloseSupportRequestAction;
use App\Application\Support\Actions\ReplyToSupportRequestAsUserAction;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
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
        ReplyToSupportRequestAsUserAction $replyToSupportRequest,
    ): void {
        $validated = $this->validate([
            'reply' => ['required', 'string', 'max:10000'],
        ]);

        $replyToSupportRequest->execute(
            user: $this->user(),
            supportRequestId: $this->supportRequestId,
            message: (string) $validated['reply'],
        );

        $this->reset('reply');
        $this->resetValidation();
        $this->statusMessage = 'پاسخ شما با موفقیت ثبت شد.';

        $this->dispatch('support-message-sent');
    }

    public function close(
        CloseSupportRequestAction $closeSupportRequest,
    ): void {
        $closeSupportRequest->execute(
            actor: $this->user(),
            supportRequestId: $this->supportRequestId,
        );

        $this->resetValidation();
        $this->statusMessage = 'این درخواست بسته شد.';
    }

    public function render(): View
    {
        return view(
            'livewire.support.show',
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
            ->whereKey($this->supportRequestId)
            ->where('user_id', $this->user()->getKey());

        if ($withRelations) {
            $query->with([
                'server',
                'messages.author.profile',
            ]);
        }

        /** @var SupportRequest $supportRequest */
        $supportRequest = $query->firstOrFail();

        return $supportRequest;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
