<?php

declare(strict_types=1);

namespace App\Livewire\Applications\AmneziaWg;

use App\Application\Applications\AmneziaWg\AmneziaWgManager;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

final class ManagementPanel extends Component
{
    use Toast;

    #[Locked]
    public int $serverId;

    public string $peerName = '';

    public array $peers = [];

    public bool $runtimeAvailable = true;

    public function mount(int $serverId): void
    {
        $server = $this->authenticatedUser()
            ->servers()
            ->whereKey($serverId)
            ->firstOrFail();

        $this->serverId = (int) $server->getKey();
        $this->reloadPeers();
    }

    public function createPeer(): void
    {
        try {
            $validated = $this->validate();
            $user = $this->authenticatedUser();
            $server = $this->ownedServer($user);

            $this->manager()->createPeer(
                user: $user,
                server: $server,
                name: $validated['peerName'],
            );

            $this->peerName = '';
            $this->resetValidation();
            $this->reloadPeers();

            $this->success('دستگاه AmneziaWG با موفقیت ساخته شد.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            $this->error('ساخت دستگاه انجام نشد. وضعیت برنامه و اتصال سرور را بررسی کنید.');
        }
    }

    public function refreshPeers(): void
    {
        $this->reloadPeers();

        if ($this->runtimeAvailable) {
            $this->success('وضعیت دستگاه‌ها بروزرسانی شد.');
        }
    }

    public function render(): View
    {
        return view('livewire.applications.amnezia-wg.management-panel');
    }

    protected function rules(): array
    {
        return [
            'peerName' => [
                'required',
                'string',
                'min:1',
                'max:60',
                'not_regex:/[\x00-\x1F\x7F]/',
            ],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'peerName' => 'نام دستگاه',
        ];
    }

    private function reloadPeers(): void
    {
        try {
            $user = $this->authenticatedUser();
            $server = $this->ownedServer($user);

            $this->peers = $this->manager()->peers(
                user: $user,
                server: $server,
            );
            $this->runtimeAvailable = true;
        } catch (Throwable) {
            $this->peers = [];
            $this->runtimeAvailable = false;
        }
    }

    private function ownedServer(User $user): Server
    {
        return Server::query()
            ->ownedBy($user)
            ->findOrFail($this->serverId);
    }

    private function manager(): AmneziaWgManager
    {
        return app(AmneziaWgManager::class);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
