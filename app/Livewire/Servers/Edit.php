<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\UpdateServerAction;
use App\Livewire\Concerns\HasServerForm;
use App\Livewire\Concerns\TestsServerConnection;
use App\Models\Server;
use Illuminate\Contracts\View\View;
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

    public function mount(Server $server): void
    {
        $this->server = $server;

        $this->fillServerForm(
            $server->only([
                'name',
                'host',
                'port',
                'username',
                'credential',
            ])
        );
    }

    public function update(UpdateServerAction $action): void
    {
        $data = $this->validate();

        if ($this->serverAlreadyExists()) {
            $this->addError(
                'host',
                'سروری با این آدرس و پورت قبلاً ثبت شده است.'
            );

            return;
        }

        $action->handle(
            $this->server,
            $data,
        );

        $this->success(
            'ذخیره شد',
            'اطلاعات سرور با موفقیت بروزرسانی شد.'
        );

        $this->redirectRoute(
            'panel.servers.index',
            navigate: true,
        );
    }

    public function render(): View
    {
        return view('livewire.servers.edit');
    }
}
