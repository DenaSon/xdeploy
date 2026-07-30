<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Applications\Actions\InstallApplicationAction;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Shared\DTOs\InstallReport;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\Server;
use Illuminate\Console\Command;

final class InstallApplicationCommand extends Command
{
    protected $signature = 'module:install {module}';

    protected $description = 'Install a module';

    public function __construct(
        private readonly InstallApplicationAction $action,
        private readonly ConnectServerAction $connect,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $value = (string) $this->argument('module');

        $type = ApplicationType::tryFrom($value);

        if ($type === null) {
            $this->error("Unknown module [{$value}].");

            return self::FAILURE;
        }

        $this->info('Connecting to server...');

        // TODO: Replace with the selected server after Server Management is implemented.
        $server = Server::first();

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
