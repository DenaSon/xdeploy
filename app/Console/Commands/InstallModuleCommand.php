<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Module\Actions\InstallModuleAction;
use App\Domain\Module\DTOs\InstallReport;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Server\Actions\ConnectServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Models\Server;
use Illuminate\Console\Command;

final class InstallModuleCommand extends Command
{
    protected $signature = 'module:install {module}';

    protected $description = 'Install a module';

    public function __construct(
        private readonly InstallModuleAction $action,
        private readonly ConnectServerAction $connect,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $value = (string) $this->argument('module');

        $type = ModuleType::tryFrom($value);

        if ($type === null) {
            $this->error("Unknown module [{$value}].");

            return self::FAILURE;
        }

        $this->info('Connecting to server...');

        // TODO: Replace with the selected server after Server Management is implemented.
        $server = new Server([
            'host' => '127.0.0.1',
            'port' => 2222,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'xdeploy',
        ]);

        $this->connect->handle($server);

        $this->info("Installing {$type->value}...");

        $report = $this->action->execute($type);

        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function renderReport(InstallReport $report): void
    {
        foreach ($report->messages as $message) {
            $this->info(sprintf(
                '[%s] %s',
                $message->module,
                $message->message,
            ));
        }
    }
}
