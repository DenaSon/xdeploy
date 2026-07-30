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
    protected $signature = 'application:install {application}';

    protected $description = 'Install an application';

    public function __construct(
        private readonly InstallApplicationAction $action,
        private readonly ConnectServerAction $connect,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $value = (string) $this->argument('application');

        $applicationType = ApplicationType::tryFrom($value);

        if ($applicationType === null) {
            $this->error("Unknown application [{$value}].");

            return self::FAILURE;
        }

        $this->info('Connecting to server...');

        // TODO: Replace with the selected server after Server Management is implemented.
        $server = Server::first();

        $this->connect->handle($server);

        $this->info("Installing application {$applicationType->value}...");

        $report = $this->action->execute($applicationType);

        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function renderReport(InstallReport $report): void
    {
        foreach ($report->messages as $message) {
            $this->info(sprintf(
                '[%s] %s',
                $message->application,
                $message->message,
            ));
        }
    }
}
