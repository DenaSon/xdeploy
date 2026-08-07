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
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        /*
         * Never trust global route-model binding as proof
         * that this server belongs to the authenticated user.
         */
        $this->server = $user
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        /*
         * Do NOT read credential here.
         */
        $this->fillServerForm(
            $this->server->only([
                'name',
                'host',
                'port',
                'username',
            ]),
        );
    }

    /**
     * Existing credentials are optional during edit.
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
         * Ignore the current server itself while checking
         * for duplicate host + port.
         */
        if (
            $this->serverAlreadyExists(
                $this->server,
            )
        ) {
            $this->addError(
                'host',
                'سروری با این آدرس و پورت قبلاً ثبت شده است.',
            );

            return;
        }

        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->server = $action->handle(
            user: $user,
            server: $this->server,
            attributes: $data,
        );

        /*
         * Never retain a submitted credential in
         * Livewire state after update.
         */
        $this->credential = '';

        $this->success(
            'ذخیره شد',
            'اطلاعات سرور با موفقیت بروزرسانی شد.',
        );

        $this->redirectRoute(
            'panel.servers.index',
            navigate: true,
        );
    }

    /**
     * When editing, an empty credential field means:
     * use the currently stored credential only on the backend
     * for the connection test.
     */
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
}
