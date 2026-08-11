<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\UpdateServerAction;
use App\Livewire\Concerns\HasServerForm;
use App\Livewire\Concerns\TestsServerConnection;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.guest')]
final class Edit extends Component
{
    use HasServerForm;
    use TestsServerConnection;
    use Toast;

    public Server $server;

    public function mount(
        Server $server,
    ): void {
        $this->server = $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        $this->fillServerForm(
            $this->server->only([
                'host',
                'port',
                'username',
            ]),
        );
    }

    /**
     * Host remains in validation because the connection-test workflow
     * requires it, but update() never persists it.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return $this->serverRules(
            requireCredential: false,
        );
    }

    public function update(
        UpdateServerAction $action,
    ): void {
        $data = $this->validate();

        /*
         * Host is immutable after Server registration.
         * UpdateServerAction also protects this invariant.
         */
        unset(
            $data['host'],
        );

        $this->server = $action->handle(
            user: $this->authenticatedUser(),
            server: $this->server,
            attributes: $data,
        );

        $this->credential = '';

        $this->success(
            'ذخیره شد',
            'اطلاعات اتصال سرور با موفقیت به‌روزرسانی شد.',
        );

        $this->redirectRoute(
            'panel.servers.index',
            navigate: true,
        );
    }

    protected function credentialForConnectionTest(): string
    {
        if ($this->credential !== '') {
            return $this->credential;
        }

        return (string) $this->server->credential;
    }

    public function render(): View
    {
        return view(
            'livewire.servers.edit',
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
