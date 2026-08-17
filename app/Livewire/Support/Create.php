<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Application\Support\SupportAttachmentPolicy;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.panel')]
#[Title('درخواست پشتیبانی جدید')]
final class Create extends Component
{
    use WithFileUploads;

    public string $subject = '';

    public string $category = SupportRequestCategory::Technical->value;

    public string $serverId = '';

    public string $message = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

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

    public function submit(
        CreateSupportRequestAction $createSupportRequest,
    ) {
        $validated = $this->validate(array_merge(
            [
                'subject' => ['required', 'string', 'max:160'],
                'category' => [
                    'required',
                    'string',
                    'in:technical,billing,account,other',
                ],
                'serverId' => ['nullable', 'integer'],
                'message' => ['required', 'string', 'max:10000'],
            ],
            $this->attachmentRules(),
        ));

        $supportRequest = $createSupportRequest->execute(
            user: $this->user(),
            subject: (string) $validated['subject'],
            category: SupportRequestCategory::from(
                (string) $validated['category'],
            ),
            message: (string) $validated['message'],
            serverId: $this->normalizedServerId(
                $validated['serverId'] ?? null,
            ),
            attachments: $this->attachments,
        );

        return redirect()->route(
            'panel.support.show',
            [
                'supportRequestId' => $supportRequest->getKey(),
            ],
        );
    }

    public function render(): View
    {
        $servers = Server::query()
            ->where('user_id', $this->user()->getKey())
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'host',
            ]);

        return view(
            'livewire.support.create',
            [
                'servers' => $servers,
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

    private function normalizedServerId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
