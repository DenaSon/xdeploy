<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Application\Server\Actions\RecordSupportAccessAction;
use App\Application\Server\Actions\TestSupportConnectionAction;
use App\Application\Server\Actions\UpdateServerConnectionHostAction;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Order;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use App\Support\Admin\AdminSupportAccessSession;
use App\Support\Admin\PendingSupportPasskeyVerification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
#[Title('جزئیات سرور')]
final class Show extends Component
{
    public Server $server;

    public string $newHost = '';

    public string $hostUpdateReason = '';

    public ?string $hostUpdateMessage = null;

    public string $supportReason = '';

    public bool $supportAccessConfirmed = false;

    public ?bool $connectionTestPassed = null;

    public ?string $connectionTestMessage = null;

    public function mount(Server $adminServer): void
    {
        $this->server = $adminServer;
        $this->newHost = (string) $adminServer->host;

        $this->supportAccessConfirmed = app(
            AdminSupportAccessSession::class,
        )->isGranted(
            admin: $this->adminUser(),
            server: $this->server,
        );
    }

    public function updateServerConnectionHost(
        UpdateServerConnectionHostAction $updateHost,
    ): void {
        $this->newHost = trim($this->newHost);
        $this->hostUpdateReason = trim($this->hostUpdateReason);
        $this->hostUpdateMessage = null;

        $validated = $this->validate(
            [
                'newHost' => [
                    'required',
                    'string',
                    'ipv4',
                    'max:45',
                ],
                'hostUpdateReason' => [
                    'required',
                    'string',
                    'min:5',
                    'max:500',
                ],
            ],
            [
                'newHost.required' => 'آدرس IP جدید را وارد کنید.',
                'newHost.ipv4' => 'آدرس واردشده باید یک IPv4 معتبر باشد.',
                'hostUpdateReason.required' => 'دلیل تغییر IP را وارد کنید.',
                'hostUpdateReason.min' => 'دلیل تغییر IP باید حداقل ۵ کاراکتر باشد.',
            ],
        );

        $oldHost = (string) $this->server->host;

        $updatedServer = $updateHost->handle(
            admin: $this->adminUser(),
            server: $this->server,
            newHost: $validated['newHost'],
            reason: $validated['hostUpdateReason'],
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        $this->server = $updatedServer;
        $this->newHost = (string) $updatedServer->host;
        $this->hostUpdateReason = '';
        $this->connectionTestPassed = null;
        $this->connectionTestMessage = null;
        $this->hostUpdateMessage = sprintf(
            'آدرس IP سرور از %s به %s تغییر کرد.',
            $oldHost,
            $updatedServer->host,
        );
    }

    public function prepareSupportPasskeyVerification(
        PendingSupportPasskeyVerification $pendingVerification,
        AdminSupportAccessSession $supportAccessSession,
    ): void {
        $reason = $this->validateSupportReason();
        $admin = $this->adminUser();

        $supportAccessSession->revoke();
        $pendingVerification->prepare(
            admin: $admin,
            server: $this->server,
            reason: $reason,
        );

        $this->supportAccessConfirmed = false;
    }

    public function testSupportConnection(
        TestSupportConnectionAction $testConnection,
        RecordSupportAccessAction $recordSupportAccess,
    ): void {
        $reason = $this->validateSupportReason();
        $admin = $this->adminUser();
        $successful = false;

        try {
            $testConnection->handle(
                $this->server,
            );

            $successful = true;
            $this->connectionTestPassed = true;
            $this->connectionTestMessage = 'اتصال SSH با موفقیت برقرار شد.';
        } catch (Throwable $exception) {
            report($exception);

            $this->connectionTestPassed = false;
            $this->connectionTestMessage = 'اتصال SSH برقرار نشد. لاگ‌های سیستم را برای جزئیات بیشتر بررسی کنید.';
        } finally {
            $recordSupportAccess->handle(
                admin: $admin,
                server: $this->server,
                action: SupportAccessAction::SshConnectionTest,
                reason: $reason,
                successful: $successful,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );
        }
    }

    public function render(): View
    {
        $server = $this->server->load('user');

        return view(
            'livewire.admin.servers.show',
            [
                'server' => $server,
                'orders' => Order::query()
                    ->where('server_id', $server->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'supportAccessLogs' => SupportAccessLog::query()
                    ->with('adminUser')
                    ->where('server_id', $server->getKey())
                    ->whereIn('action', [
                        SupportAccessAction::SshConnectionTest->value,
                        SupportAccessAction::PasskeyConfirmed->value,
                        SupportAccessAction::CredentialRevealed->value,
                    ])
                    ->latest('id')
                    ->limit(10)
                    ->get(),
            ],
        );
    }

    private function validateSupportReason(): string
    {
        $validated = $this->validate([
            'supportReason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        return trim($validated['supportReason']);
    }

    private function adminUser(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        return $user;
    }
}
