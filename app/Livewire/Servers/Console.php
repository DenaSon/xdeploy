<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Cloud\ServerConsole\Actions\GetCloudServerConsoleAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
#[Title('کنسول سرور')]
final class Console extends Component
{
    public Server $server;

    public ?string $consoleUrl = null;

    public ?string $consoleError = null;

    public function mount(
        Server $server,
        CloudServerCapabilityResolver $capabilities,
    ): void {
        $this->server = $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        abort_unless(
            $this->server->isCloudProvisioned()
            && $capabilities->supports(
                server: $this->server,
                capability: CloudServerConsoleInterface::class,
            ),
            404,
        );
    }

    public function loadConsole(
        GetCloudServerConsoleAction $action,
    ): void {
        $this->consoleUrl = null;
        $this->consoleError = null;

        try {
            $console = $action->execute(
                $this->server,
            );

            $this->consoleUrl = $console->url;
        } catch (Throwable $exception) {
            report($exception);

            $this->consoleError =
                'برقراری اتصال به کنسول سرور ناموفق بود.';
        }
    }

    public function render(): View
    {
        return view(
            'livewire.servers.console',
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
