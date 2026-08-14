<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Concerns\HasServerForm;
use App\Livewire\Concerns\TestsServerConnection;
use App\Models\User;
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

        if (! $this->connectionIsVerified()) {
            $this->error(
                'بررسی اتصال لازم است',
                'قبل از افزودن سرور، اتصال را با اطلاعات فعلی بررسی کنید.',
            );

            return null;
        }

        if ($this->serverAlreadyExists()) {
            $this->addError(
                'host',
                'سروری با این آدرس و پورت قبلاً ثبت شده است.',
            );

            return null;
        }

        $user = $this->authenticatedUser();

        $data['authentication_type'] =
            AuthenticationType::Password->value;

        $server = $action->handle(
            user: $user,
            attributes: $data,
            status: ServerStatus::Active,
        );

        $this->credential = '';

        $this->success(
            'سرور اضافه شد',
            'سرور با موفقیت به Coreflare متصل شد.',
        );

        return $this->redirectRoute(
            'panel.servers.dashboard',
            [
                'server' => $server,
            ],
            navigate: true,
        );
    }

    public function render(): View
    {
        return view(
            'livewire.servers.create',
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
