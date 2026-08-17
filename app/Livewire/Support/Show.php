<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Application\Support\Actions\CloseSupportRequestAction;
use App\Application\Support\Actions\ReplyToSupportRequestAsUserAction;
use App\Application\Support\SupportAttachmentPolicy;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.panel')]
#[Title('درخواست پشتیبانی')]
final class Show extends Component
{
    use WithFileUploads;

    public int $supportRequestId;

    public string $reply = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public ?string $statusMessage = null;

    public function mount(string $supportRequestId): void
    {
        abort_unless(ctype_digit($supportRequestId), 404);

        $this->supportRequestId = (int) $supportRequestId;

        $this->supportRequest();
    }

    public function updatedAttachments(): void
    {
        $this->validate($this->attachmentRules());
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
        $this->resetValidation();
    }

    public function sendReply(
        ReplyToSupportRequestAsUserAction $replyToSupportRequest,
    ): void {
        $validated = $this->validate(array_merge(
            [
                'reply' => ['required', 'string', 'max:10000'],
            ],
            $this->attachmentRules(),
        ));

        $replyToSupportRequest->execute(
            user: $this->user(),
            supportRequestId: $this->supportRequestId,
            message: (string) $validated['reply'],
            attachments: $this->attachments,
        );

        $this->reset('reply', 'attachments');
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

    /**
     * @return array<string, array<int, string>>
     */
    private function attachmentRules(): array
    {
        return [
            'attachments' => [
                'array',
                'max:'.SupportAttachmentPolicy::MAX_FILES,
            ],
            'attachments.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.SupportAttachmentPolicy::MAX_KILOBYTES,
                'dimensions:max_width='.
                    SupportAttachmentPolicy::MAX_SOURCE_DIMENSION.
                    ',max_height='.
                    SupportAttachmentPolicy::MAX_SOURCE_DIMENSION,
            ],
        ];
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
                'messages.attachments',
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
