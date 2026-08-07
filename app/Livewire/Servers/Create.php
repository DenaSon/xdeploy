<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Livewire\Concerns\HasServerForm;
use App\Livewire\Concerns\TestsServerConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.guest')]
final class Create extends Component
{
    use HasServerForm;
    use TestsServerConnection;
    use Toast;

    public function mount(): void
    {
        $this->port = 22;
        $this->username = 'root';
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return $this->serverRules(
            requireCredential: true,
        );
    }

    public function save(
        CreateServerAction $action,
    ): mixed {
        $data = $this->validate();

        if ($this->serverAlreadyExists()) {
            $this->addError(
                'host',
                'سروری با این آدرس و پورت قبلاً ثبت شده است.',
            );

            return null;
        }

        $data['authentication_type'] =
            AuthenticationType::Password->value;

        $action->handle(
            Auth::user(),
            $data,
        );

        $this->success(
            'سرور با موفقیت ایجاد شد.',
        );

        return $this->redirectRoute(
            'panel.servers.index',
            navigate: true,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.servers.create',
        );
    }
}
