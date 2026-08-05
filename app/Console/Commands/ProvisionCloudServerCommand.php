<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudProviderException;
use App\Domain\Cloud\Exceptions\CloudProvisioningTimeoutException;
use App\Domain\Cloud\Exceptions\CloudServerNotReadyException;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ProvisionCloudServerCommand extends Command
{
    protected $signature = 'cloud:provision-server
        {user : The xDeploy user ID}
        {--name= : Cloud server name}
        {--region= : Cloud region ID}
        {--size= : Cloud size or flavor ID}
        {--image= : Cloud image ID}
        {--network= : Cloud network ID}
        {--security-group=* : Security group ID; may be repeated}
        {--disk= : Root disk size in GiB}
        {--execute : Confirm that a real billable server may be created}';

    protected $description =
        'Create and verify a real cloud server through the xDeploy provisioning workflow.';

    public function handle(): int
    {
        if (! $this->option('execute')) {
            $this->components->error(
                'This command creates a real billable cloud server. Re-run it with --execute.',
            );

            return self::INVALID;
        }

        $user = $this->resolveUser();

        if (! $user instanceof User) {
            return self::FAILURE;
        }

        $data = null;

        try {
            $data = $this->createProvisioningData();

            $provider = $this->requiredConfigString(
                'cloud.default',
            );

            $this->displayProvisioningSummary(
                user: $user,
                provider: $provider,
                data: $data,
            );

            $this->newLine();

            $this->components->warn(
                'This operation creates a real cloud server and may incur provider charges.',
            );

            $this->components->warn(
                'Automatic cloud-server deletion is not implemented. Cleanup must be performed manually.',
            );

            if (
                ! $this->confirm(
                    'Create this billable cloud server now?',
                    false,
                )
            ) {
                $this->components->info(
                    'Provisioning cancelled.',
                );

                return self::SUCCESS;
            }

            $this->newLine();

            $this->components->info(
                'Starting real cloud provisioning...',
            );

            /** @var ProvisionCloudServerAction $action */
            $action = resolve(
                ProvisionCloudServerAction::class,
            );

            $result = $action->handle(
                user: $user,
                data: $data,
            );

            $this->newLine();

            $this->components->info(
                'Cloud server provisioning completed successfully.',
            );

            $this->table(
                ['Field', 'Value'],
                [
                    [
                        'xDeploy Server ID',
                        (string) $result->server->id,
                    ],
                    [
                        'Provider Server ID',
                        (string) $result->server->cloud_server_id,
                    ],
                    [
                        'Provider',
                        (string) $result->server->cloud_provider,
                    ],
                    [
                        'Region',
                        (string) $result->server->cloud_region,
                    ],
                    [
                        'Host',
                        (string) $result->server->host,
                    ],
                    [
                        'Port',
                        (string) $result->server->port,
                    ],
                    [
                        'Username',
                        (string) $result->server->username,
                    ],
                    [
                        'Status',
                        $result->server->status->value,
                    ],
                    [
                        'Provider Poll Attempts',
                        (string) $result->pollAttempts,
                    ],
                ],
            );

            $this->components->info(
                'The generated credential was encrypted and was not printed.',
            );

            return self::SUCCESS;
        } catch (CloudProviderException $exception) {
            /*
             * Report the original exception to Laravel logs while keeping
             * terminal output sanitized and free from provider secrets.
             */
            report($exception);

            $this->newLine();

            $this->components->error(
                $this->safeCloudFailureMessage(
                    $exception,
                ),
            );

            $this->reportRecoverableServer(
                user: $user,
                serverName: $data?->name,
            );

            return self::FAILURE;
        } catch (Throwable $exception) {
            /*
             * Unexpected application errors must also be logged with their
             * full stack trace, but never printed directly to the terminal.
             */
            report($exception);

            $this->newLine();

            $this->components->error(
                'Cloud provisioning failed because of an unexpected internal error.',
            );

            $this->reportRecoverableServer(
                user: $user,
                serverName: $data?->name,
            );

            return self::FAILURE;
        }
    }

    private function resolveUser(): ?User
    {
        $argument = $this->argument(
            'user',
        );

        if (
            ! is_string($argument)
            && ! is_int($argument)
        ) {
            $this->components->error(
                'The xDeploy user ID is invalid.',
            );

            return null;
        }

        $userId = (string) $argument;

        if (
            ! ctype_digit($userId)
            || (int) $userId < 1
        ) {
            $this->components->error(
                'The xDeploy user ID must be a positive integer.',
            );

            return null;
        }

        $user = User::query()->find(
            (int) $userId,
        );

        if (! $user instanceof User) {
            $this->components->error(
                sprintf(
                    'xDeploy user [%s] was not found.',
                    $userId,
                ),
            );

            return null;
        }

        return $user;
    }

    private function createProvisioningData(): CreateCloudServerData
    {
        $provider = $this->requiredConfigString(
            'cloud.default',
        );

        $configPrefix =
            "cloud.providers.{$provider}";

        $name = $this->optionalStringOption(
            'name',
        ) ?? $this->generateServerName();

        $region = $this->stringOptionOrConfig(
            option: 'region',
            configKey: "{$configPrefix}.region",
        );

        $sizeId = $this->stringOptionOrConfig(
            option: 'size',
            configKey: "{$configPrefix}.defaults.size_id",
        );

        $imageId = $this->stringOptionOrConfig(
            option: 'image',
            configKey: "{$configPrefix}.defaults.image_id",
        );

        $networkId = $this->stringOptionOrConfig(
            option: 'network',
            configKey: "{$configPrefix}.defaults.network_id",
        );

        $securityGroupIds =
            $this->securityGroupIds(
                "{$configPrefix}.defaults.security_group_id",
            );

        $diskGiB = $this->positiveIntegerOptionOrConfig(
            option: 'disk',
            configKey: "{$configPrefix}.defaults.disk_size",
        );

        $initializationScript = config(
            "{$configPrefix}.defaults.init_script",
            '',
        );

        if (! is_string($initializationScript)) {
            throw new CloudConfigurationException(
                'Cloud initialization script must be a string.',
            );
        }

        $highAvailability = filter_var(
            config(
                "{$configPrefix}.defaults.ha_enabled",
                false,
            ),
            FILTER_VALIDATE_BOOL,
        );

        return new CreateCloudServerData(
            name: $name,
            regionId: $region,
            sizeId: $sizeId,
            imageId: $imageId,
            networkId: $networkId,
            securityGroupIds: $securityGroupIds,
            diskGiB: $diskGiB,
            sshKeyName: null,
            initializationScript: $initializationScript,
            highAvailability: $highAvailability,
        );
    }

    private function displayProvisioningSummary(
        User $user,
        string $provider,
        CreateCloudServerData $data,
    ): void {
        $this->newLine();

        $this->components->info(
            'Real cloud provisioning summary',
        );

        $this->table(
            ['Field', 'Value'],
            [
                [
                    'User ID',
                    (string) $user->getKey(),
                ],
                [
                    'User',
                    (string) $user->name,
                ],
                [
                    'Provider',
                    $provider,
                ],
                [
                    'Server Name',
                    $data->name,
                ],
                [
                    'Region',
                    $data->regionId,
                ],
                [
                    'Size',
                    $data->sizeId,
                ],
                [
                    'Image',
                    $data->imageId,
                ],
                [
                    'Network',
                    $data->networkId,
                ],
                [
                    'Security Groups',
                    implode(
                        ', ',
                        $data->securityGroupIds,
                    ),
                ],
                [
                    'Disk',
                    "{$data->diskGiB} GiB",
                ],
                [
                    'Authentication',
                    'Generated password',
                ],
            ],
        );
    }

    private function optionalStringOption(
        string $name,
    ): ?string {
        $value = $this->option(
            $name,
        );

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private function stringOptionOrConfig(
        string $option,
        string $configKey,
    ): string {
        $optionValue =
            $this->optionalStringOption(
                $option,
            );

        if ($optionValue !== null) {
            return $optionValue;
        }

        return $this->requiredConfigString(
            $configKey,
        );
    }

    /**
     * @return list<string>
     */
    private function securityGroupIds(
        string $configKey,
    ): array {
        $values = $this->option(
            'security-group',
        );

        $ids = [];

        if (is_array($values)) {
            foreach ($values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                $value = trim($value);

                if ($value !== '') {
                    $ids[] = $value;
                }
            }
        }

        if ($ids === []) {
            $ids[] = $this->requiredConfigString(
                $configKey,
            );
        }

        return array_values(
            array_unique($ids),
        );
    }

    private function positiveIntegerOptionOrConfig(
        string $option,
        string $configKey,
    ): int {
        $optionValue = $this->option(
            $option,
        );

        $value = $optionValue !== null
            ? $optionValue
            : config($configKey);

        if (
            ! is_int($value)
            && ! is_numeric($value)
        ) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud configuration [%s] must be an integer.',
                    $configKey,
                ),
            );
        }

        $value = (int) $value;

        if ($value < 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud configuration [%s] must be greater than zero.',
                    $configKey,
                ),
            );
        }

        return $value;
    }

    private function requiredConfigString(
        string $key,
    ): string {
        $value = config(
            $key,
        );

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new CloudConfigurationException(
                sprintf(
                    'Required cloud configuration [%s] is missing.',
                    $key,
                ),
            );
        }

        return trim($value);
    }

    private function generateServerName(): string
    {
        return sprintf(
            'xdeploy-e2e-%s-%s',
            now()->format(
                'Ymd-His',
            ),
            Str::lower(
                Str::random(6),
            ),
        );
    }

    private function safeCloudFailureMessage(
        CloudProviderException $exception,
    ): string {
        return match (true) {
            $exception instanceof CloudInsufficientBalanceException => 'Cloud provider balance is insufficient.',

            $exception instanceof CloudAuthenticationException => 'Cloud provider authentication failed.',

            $exception instanceof CloudAuthorizationException => 'Cloud provider permission is insufficient.',

            $exception instanceof CloudProvisioningTimeoutException => 'Cloud server did not become ready before the provisioning timeout.',

            $exception instanceof CloudServerSshUnavailableException => 'Cloud server was created, but SSH readiness verification failed.',

            $exception instanceof CloudServerNotReadyException => 'Cloud server became active but was not ready for use.',

            $exception instanceof CloudConnectionException => 'Could not communicate with the cloud provider.',

            $exception instanceof CloudUnexpectedResponseException => 'Cloud provider returned an unexpected create response.',

            $exception instanceof CloudValidationException => 'Cloud provider rejected the provisioning request.',

            $exception instanceof CloudConfigurationException => 'Cloud provisioning configuration is invalid.',

            default => 'Cloud server provisioning failed.',
        };
    }

    private function reportRecoverableServer(
        User $user,
        ?string $serverName,
    ): void {
        if ($serverName === null) {
            return;
        }

        $server = Server::query()
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->where(
                'name',
                $serverName,
            )
            ->whereNotNull(
                'cloud_server_id',
            )
            ->latest('id')
            ->first();

        if (! $server instanceof Server) {
            return;
        }

        $this->newLine();

        $this->components->warn(
            'A recoverable inactive server record exists. Do not run provisioning again before checking it.',
        );

        $this->table(
            ['Field', 'Value'],
            [
                [
                    'xDeploy Server ID',
                    (string) $server->id,
                ],
                [
                    'Provider Server ID',
                    (string) $server->cloud_server_id,
                ],
                [
                    'Host',
                    $server->host
                    ?? 'Not assigned',
                ],
                [
                    'Status',
                    $server->status->value,
                ],
            ],
        );
    }
}
