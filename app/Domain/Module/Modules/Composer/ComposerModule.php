<?php

declare(strict_types=1);

namespace App\Domain\Module\Modules\Composer;

use App\Domain\Module\Abstracts\CommandModule;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Enums\SoftwareType;
use App\Domain\Module\ValueObjects\ModuleDependency;
use App\Domain\Module\ValueObjects\ProvidedSoftware;

final readonly class ComposerModule extends CommandModule
{
    public function type(): ModuleType
    {
        return ModuleType::Composer;
    }

    public function name(): string
    {
        return 'Composer';
    }

    /**
     * @return list<ModuleDependency>
     */
    public function dependencies(): array
    {
        return [
            new ModuleDependency(ModuleType::Php),
        ];
    }

    protected function inspectCommand(): string
    {
        return 'composer --version';
    }

    protected function metadataFromOutput(string $output): array
    {
        preg_match('/\d+\.\d+\.\d+/', $output, $matches);

        return [
            'version' => $matches[0] ?? '',
        ];
    }

    protected function checkRequirements(): void
    {
        // Example:
        // - Verify Linux distribution
        // - Verify root privileges
        // - Verify supported architecture
    }

    public function provides(): array
    {
        return [
            new ProvidedSoftware(SoftwareType::Composer),
        ];
    }

    protected function installCommand(): string
    {
        return <<<'BASH'
apt-get update &&
apt-get install -y curl &&
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
BASH;
    }

    public function start(): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function stop(): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function restart(): void
    {
        throw new \LogicException('Not implemented.');
    }

    protected function uninstallCommand(): string
    {
        return <<<'BASH'
rm -f /usr/local/bin/composer
BASH;
    }
}
