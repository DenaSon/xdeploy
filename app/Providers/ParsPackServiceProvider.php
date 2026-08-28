<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Infrastructure\Cloud\ParsPack\Mappers\ParsPackCloudResponseMapper;
use App\Infrastructure\Cloud\ParsPack\ParsPackCloudClient;
use App\Infrastructure\Cloud\ParsPack\ParsPackCloudProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ParsPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configuration = config('parspack');

        if (! is_array($configuration)) {
            throw new CloudConfigurationException(
                'ParsPack cloud configuration is unavailable.',
            );
        }

        /*
         * Keep availability discovery uniform for the central cloud registry
         * without coupling the large shared cloud.php file to this adapter.
         */
        config()->set(
            'cloud.providers.parspack',
            $configuration,
        );

        $this->app->singleton(
            ParsPackCloudResponseMapper::class,
        );

        $this->app->singleton(
            ParsPackCloudClient::class,
            static function (): ParsPackCloudClient {
                $baseUrl = config('parspack.base_url');
                $apiToken = config('parspack.api_token');
                $connectTimeout = config('parspack.timeouts.connect', 10);
                $requestTimeout = config('parspack.timeouts.request', 90);
                $catalogConnectTimeout = config('cloud.transport.catalog.connect', 3);
                $catalogRequestTimeout = config('cloud.transport.catalog.request', 8);
                $retryMaxAttempts = config('cloud.transport.safe_read_retry.max_attempts', 2);
                $retryDelayMilliseconds = config('cloud.transport.safe_read_retry.delay_milliseconds', 250);

                if (! is_string($baseUrl) || trim($baseUrl) === '') {
                    throw new CloudConfigurationException(
                        'ParsPack base URL is not configured.',
                    );
                }

                if (! is_string($apiToken) || trim($apiToken) === '') {
                    throw new CloudConfigurationException(
                        'ParsPack API token is not configured.',
                    );
                }

                foreach ([
                    'ParsPack connect timeout' => $connectTimeout,
                    'ParsPack request timeout' => $requestTimeout,
                    'Cloud catalog connect timeout' => $catalogConnectTimeout,
                    'Cloud catalog request timeout' => $catalogRequestTimeout,
                    'Cloud safe-read retry max attempts' => $retryMaxAttempts,
                    'Cloud safe-read retry delay' => $retryDelayMilliseconds,
                ] as $label => $value) {
                    if (! is_int($value) && ! is_numeric($value)) {
                        throw new CloudConfigurationException(
                            sprintf('%s must be an integer.', $label),
                        );
                    }
                }

                return new ParsPackCloudClient(
                    baseUrl: trim($baseUrl),
                    apiToken: trim($apiToken),
                    connectTimeout: (int) $connectTimeout,
                    requestTimeout: (int) $requestTimeout,
                    catalogConnectTimeout: (int) $catalogConnectTimeout,
                    catalogRequestTimeout: (int) $catalogRequestTimeout,
                    retryMaxAttempts: (int) $retryMaxAttempts,
                    retryDelayMilliseconds: (int) $retryDelayMilliseconds,
                );
            },
        );

        $this->app->singleton(
            ParsPackCloudProvider::class,
            static function (Application $app): ParsPackCloudProvider {
                $sshKeyId = config('parspack.bootstrap.ssh_key_id');
                $privateKeyBase64 = config(
                    'parspack.bootstrap.private_key_base64',
                );
                $fundingOverheadPercent = config(
                    'parspack.funding_overhead_percent',
                    0,
                );

                if (! is_int($sshKeyId) && ! is_numeric($sshKeyId)) {
                    throw new CloudConfigurationException(
                        'ParsPack bootstrap SSH key ID must be an integer.',
                    );
                }

                if (
                    ! is_string($privateKeyBase64)
                    || trim($privateKeyBase64) === ''
                ) {
                    throw new CloudConfigurationException(
                        'ParsPack bootstrap SSH private key is not configured.',
                    );
                }

                $privateKey = base64_decode(
                    trim($privateKeyBase64),
                    true,
                );

                if (! is_string($privateKey) || trim($privateKey) === '') {
                    throw new CloudConfigurationException(
                        'ParsPack bootstrap SSH private key is not valid base64.',
                    );
                }

                if (
                    ! is_int($fundingOverheadPercent)
                    && ! is_numeric($fundingOverheadPercent)
                ) {
                    throw new CloudConfigurationException(
                        'ParsPack funding overhead percent must be an integer.',
                    );
                }

                return new ParsPackCloudProvider(
                    client: $app->make(ParsPackCloudClient::class),
                    mapper: $app->make(ParsPackCloudResponseMapper::class),
                    bootstrapSshKeyId: (int) $sshKeyId,
                    bootstrapPrivateKey: $privateKey,
                    fundingOverheadPercent: (int) $fundingOverheadPercent,
                );
            },
        );
    }
}
