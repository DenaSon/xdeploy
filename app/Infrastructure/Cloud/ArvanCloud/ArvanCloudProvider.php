<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CloudSshKeyData;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;

final readonly class ArvanCloudProvider implements CloudProviderInterface
{
    public function __construct(
        private ArvanCloudClient $client,
        private ArvanCloudResponseMapper $mapper,
    ) {}

    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->get('regions'),
        );
    }

    /**
     * @return list<CloudSizeData>
     */
    public function listSizes(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapSizes(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'sizes',
                ),
            ),
            $regionId,
        );
    }

    /**
     * @return list<CloudImageData>
     */
    public function listImages(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapImages(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'images',
                ),
                [
                    'type' => 'distributions',
                ],
            ),
            $regionId,
        );
    }

    /**
     * @return list<CloudNetworkData>
     */
    public function listNetworks(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapNetworks(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'networks',
                ),
            ),
            $regionId,
        );
    }

    /**
     * @return list<CloudSecurityGroupData>
     */
    public function listSecurityGroups(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapSecurityGroups(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'securities',
                ),
            ),
            $regionId,
        );
    }

    public function getQuota(string $region): CloudQuotaData
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapQuota(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'quota',
                ),
            ),
            $regionId,
        );
    }

    /**
     * @return list<CloudSshKeyData>
     */
    public function listSshKeys(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapSshKeys(
            $this->client->get(
                $this->regionEndpoint(
                    $regionId,
                    'ssh-keys',
                ),
            ),
            $regionId,
        );
    }

    private function regionEndpoint(
        string $region,
        string $resource,
    ): string {
        return sprintf(
            'regions/%s/%s',
            rawurlencode($region),
            $resource,
        );
    }

    private function normalizeRegion(string $region): string
    {
        if (
            $region === ''
            || preg_match('/[\x00-\x1F\x7F]/', $region) === 1
        ) {
            throw new CloudValidationException(
                'Cloud region identifier is invalid.',
            );
        }

        $region = trim($region);

        if ($region === '') {
            throw new CloudValidationException(
                'Cloud region identifier cannot be empty.',
            );
        }

        if (
            preg_match(
                '/\A[a-zA-Z0-9]+(?:[._-][a-zA-Z0-9]+)*\z/',
                $region,
            ) !== 1
        ) {
            throw new CloudValidationException(
                'Cloud region identifier is invalid.',
            );
        }

        return $region;
    }
}
