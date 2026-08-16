<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Liara\Mappers;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use DateTimeImmutable;
use Throwable;

final readonly class LiaraCloudResponseMapper
{
    private const string CURRENCY = 'IRR';

    private const string DEFAULT_USERNAME = 'root';

    /**
     * Liara's public pricing and official CLI describe the IaaS plan
     * prices returned by /plans as Tomans. Coreflare normalizes all
     * provider prices to Iranian Rials at the provider boundary.
     */
    private const int TOMAN_TO_RIAL = 10;

    /**
     * @param  array<array-key, mixed>  $response
     * @return list<CloudRegionData>
     */
    public function mapRegions(array $response): array
    {
        $plans = $this->plans($response);
        $regions = [];

        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                throw $this->unexpected('Liara plan payload must be an object.');
            }

            $region = $this->requiredString($plan, 'region');

            if (isset($regions[$region])) {
                continue;
            }

            $regions[$region] = new CloudRegionData(
                id: $region,
                displayName: $region === 'iran' ? 'Iran' : $region,
                country: $region === 'iran' ? 'IR' : null,
                city: null,
                dataCenter: null,
                canCreateServers: true,
                isVisible: true,
                supportsVolumeBacked: true,
            );
        }

        return array_values($regions);
    }

    /**
     * @param  array<array-key, mixed>  $response
     * @return list<CloudSizeData>
     */
    public function mapSizes(
        array $response,
        string $region,
    ): array {
        $region = trim($region);
        $sizes = [];

        foreach ($this->plans($response) as $id => $plan) {
            if (! is_string($id) || trim($id) === '' || ! is_array($plan)) {
                throw $this->unexpected('Liara plan catalog contains an invalid entry.');
            }

            if ($this->requiredString($plan, 'region') !== $region) {
                continue;
            }

            if (! $this->booleanValue($plan['available'] ?? null, 'available')) {
                continue;
            }

            $cpu = $this->nestedPositiveInt($plan, 'CPU', 'amount');
            $ramGiB = $this->nestedPositiveInt($plan, 'RAM', 'amount');
            $volume = $this->positiveInt($plan['volume'] ?? null, 'volume');

            $sizes[] = new CloudSizeData(
                id: trim($id),
                name: trim($id),
                regionId: $region,
                vCpu: $cpu,
                memoryMiB: $ramGiB * 1024,
                diskGiB: $volume,
                category: 'liara',
                hourlyPrice: new CloudPriceData(
                    amount: $this->tomanToRial($plan['hourlyPrice'] ?? null),
                    currencyCode: self::CURRENCY,
                    billingPeriod: CloudBillingPeriod::Hourly,
                ),
                monthlyPrice: new CloudPriceData(
                    amount: $this->tomanToRial($plan['monthlyPrice'] ?? null),
                    currencyCode: self::CURRENCY,
                    billingPeriod: CloudBillingPeriod::Monthly,
                ),
            );
        }

        return $sizes;
    }

    /**
     * @param  array<array-key, mixed>  $response
     * @return list<CloudImageData>
     */
    public function mapImages(
        array $response,
        string $region,
    ): array {
        if ($response === []) {
            throw $this->unexpected('Liara operating system catalog is empty.');
        }

        $images = [];

        foreach ($response as $distribution => $versions) {
            if (
                ! is_string($distribution)
                || trim($distribution) === ''
                || ! is_array($versions)
            ) {
                throw $this->unexpected('Liara operating system catalog contains an invalid entry.');
            }

            $distribution = strtolower(trim($distribution));

            foreach ($versions as $version) {
                if (! is_string($version) || trim($version) === '') {
                    throw $this->unexpected('Liara operating system version is invalid.');
                }

                $version = trim($version);
                $id = sprintf('%s-%s', $distribution, $version);

                $images[] = new CloudImageData(
                    id: $id,
                    name: $id,
                    regionId: trim($region),
                    distribution: $distribution,
                    version: $version,
                    architecture: null,
                    minDiskGiB: null,
                    minMemoryMiB: null,
                    supportsSshKey: true,
                    supportsPassword: true,
                );
            }
        }

        return $images;
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    public function mapCreatedServer(
        array $response,
        string $requestedName,
        string $region,
    ): CreatedCloudServerData {
        $serverId = $this->requiredString($response, 'VMID');

        if (preg_match('/\A[a-f0-9]{24}\z/i', $serverId) !== 1) {
            throw $this->unexpected('Liara create response contains an invalid VM identifier.');
        }

        return new CreatedCloudServerData(
            id: $serverId,
            name: trim($requestedName),
            regionId: trim($region),
            status: CloudServerStatus::Provisioning,
            username: self::DEFAULT_USERNAME,
            createdAt: null,
            generatedPassword: null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    public function mapServer(
        array $response,
        string $region,
    ): CloudServerData {
        $id = $this->requiredString($response, '_id');
        $name = $this->requiredString($response, 'name');
        $state = strtoupper($this->requiredString($response, 'state'));
        $plan = $this->optionalString($response['plan'] ?? null);
        $image = $this->optionalString($response['OS'] ?? null);
        $planDetails = $response['planDetails'] ?? null;

        if ($planDetails !== null && ! is_array($planDetails)) {
            throw $this->unexpected('Liara VM plan details must be an object.');
        }

        return new CloudServerData(
            id: $id,
            name: $name,
            regionId: trim($region),
            status: $this->mapStatus($state),
            username: self::DEFAULT_USERNAME,
            sizeId: $plan,
            imageId: $image,
            createdAt: $this->dateTime($response['createdAt'] ?? null),
            addresses: $this->mapAddresses($response['IPs'] ?? []),
            networkIds: [],
            securityGroupIds: [],
            volumeBacked: true,
            highAvailability: false,
            sizeName: $plan,
            vCpu: is_array($planDetails)
                ? $this->optionalNestedPositiveInt($planDetails, 'CPU', 'amount')
                : null,
            memoryMiB: is_array($planDetails)
                ? $this->optionalNestedPositiveInt($planDetails, 'RAM', 'amount', 1024)
                : null,
            diskGiB: is_array($planDetails)
                ? $this->optionalPositiveInt($planDetails['volume'] ?? null)
                : null,
            taskState: $this->guestCustomizationStatus($response),
            providerError: null,
            powerState: $this->mapPowerState($response, $state),
            generatedPassword: $this->rootPassword($response),
        );
    }

    /**
     * @param  array<array-key, mixed>  $response
     * @return list<CloudServerData>
     */
    public function mapServerInventory(
        array $response,
        string $region,
    ): array {
        $vms = $response['vms'] ?? null;

        if (! is_array($vms) || ! array_is_list($vms)) {
            throw $this->unexpected('Liara VM inventory payload is invalid.');
        }

        $servers = [];

        foreach ($vms as $vm) {
            if (! is_array($vm)) {
                throw $this->unexpected('Liara VM inventory contains an invalid entry.');
            }

            $state = strtoupper($this->requiredString($vm, 'state'));
            $power = strtoupper((string) ($vm['power'] ?? ''));

            $servers[] = new CloudServerData(
                id: $this->requiredString($vm, '_id'),
                name: $this->requiredString($vm, 'name'),
                regionId: trim($region),
                status: $this->mapStatus($state),
                username: self::DEFAULT_USERNAME,
                sizeId: $this->optionalString($vm['plan'] ?? null),
                imageId: $this->optionalString($vm['OS'] ?? null),
                createdAt: $this->dateTime($vm['createdAt'] ?? null),
                addresses: [],
                networkIds: [],
                securityGroupIds: [],
                volumeBacked: true,
                highAvailability: false,
                taskState: null,
                providerError: null,
                powerState: match ($power) {
                    'POWERED_ON' => CloudServerPowerState::Running,
                    'POWERED_OFF' => CloudServerPowerState::Stopped,
                    default => $this->mapStatus($state)->isFailed()
                        ? CloudServerPowerState::Error
                        : CloudServerPowerState::Unknown,
                },
            );
        }

        return $servers;
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    public function mapRootPasswordReset(
        array $response,
    ): CloudRootPasswordResetData {
        $password = $this->requiredString($response, 'password');

        return new CloudRootPasswordResetData(
            password: $password,
            message: 'Liara root password was reset successfully.',
        );
    }

    /**
     * @param  array<array-key, mixed>  $response
     * @return array<string, mixed>
     */
    private function plans(array $response): array
    {
        $plans = $response['plans'] ?? null;

        if (! is_array($plans) || $plans === [] || array_is_list($plans)) {
            throw $this->unexpected('Liara plan catalog payload is invalid.');
        }

        return $plans;
    }

    private function mapStatus(string $state): CloudServerStatus
    {
        return match ($state) {
            'CREATED' => CloudServerStatus::Active,
            'CREATING', 'QUEUED', 'PENDING', 'UPDATING', 'RESIZING', 'DELETING' => CloudServerStatus::Provisioning,
            'FAILED', 'ERROR' => CloudServerStatus::Failed,
            default => CloudServerStatus::Unknown,
        };
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    private function mapPowerState(
        array $response,
        string $state,
    ): CloudServerPowerState {
        if ($this->mapStatus($state)->isFailed()) {
            return CloudServerPowerState::Error;
        }

        $power = strtoupper((string) ($response['power'] ?? ''));
        $guestState = strtoupper((string) ($response['guestState'] ?? ''));

        if ($power === 'POWERED_OFF') {
            return CloudServerPowerState::Stopped;
        }

        if ($power === 'POWERED_ON') {
            return $guestState === '' || $guestState === 'RUNNING'
                ? CloudServerPowerState::Running
                : CloudServerPowerState::Transitioning;
        }

        if (str_contains($power, 'POWERING')) {
            return CloudServerPowerState::Transitioning;
        }

        return CloudServerPowerState::Unknown;
    }

    /**
     * @return list<CloudServerAddressData>
     */
    private function mapAddresses(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->unexpected('Liara VM IP list is invalid.');
        }

        $addresses = [];

        foreach ($value as $ip) {
            if (! is_array($ip)) {
                throw $this->unexpected('Liara VM IP entry is invalid.');
            }

            $address = $this->requiredString($ip, 'address');
            $version = strtolower($this->requiredString($ip, 'version'));

            $ipVersion = match ($version) {
                'v4', 'ipv4' => CloudIpVersion::IPv4,
                'v6', 'ipv6' => CloudIpVersion::IPv6,
                default => throw $this->unexpected('Liara VM IP version is unsupported.'),
            };

            $addresses[] = new CloudServerAddressData(
                address: $address,
                version: $ipVersion,
                isPublic: true,
                isVpc: false,
                type: 'public',
            );
        }

        return $addresses;
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    private function rootPassword(array $response): ?string
    {
        $config = $response['config'] ?? null;

        if (! is_array($config)) {
            return null;
        }

        $password = $this->optionalString($config['rootPassword'] ?? null);

        if ($password === null || $password === '*****') {
            return null;
        }

        return $password;
    }

    /**
     * @param  array<array-key, mixed>  $response
     */
    private function guestCustomizationStatus(array $response): ?string
    {
        $guestCus = $response['guestCus'] ?? null;

        if (! is_array($guestCus)) {
            return null;
        }

        return $this->optionalString($guestCus['status'] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function requiredString(
        array $payload,
        string $key,
    ): string {
        $value = $this->optionalString($payload[$key] ?? null);

        if ($value === null) {
            throw $this->unexpected(
                sprintf('Liara response is missing required field [%s].', $key),
            );
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function booleanValue(
        mixed $value,
        string $field,
    ): bool {
        if (! is_bool($value)) {
            throw $this->unexpected(
                sprintf('Liara field [%s] must be boolean.', $field),
            );
        }

        return $value;
    }

    private function positiveInt(
        mixed $value,
        string $field,
    ): int {
        $normalized = $this->optionalPositiveInt($value);

        if ($normalized === null) {
            throw $this->unexpected(
                sprintf('Liara field [%s] must be a positive integer.', $field),
            );
        }

        return $normalized;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (
            (is_float($value) || is_string($value))
            && is_numeric($value)
            && (float) $value > 0
            && floor((float) $value) === (float) $value
        ) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function nestedPositiveInt(
        array $payload,
        string $parent,
        string $child,
    ): int {
        $container = $payload[$parent] ?? null;

        if (! is_array($container)) {
            throw $this->unexpected(
                sprintf('Liara field [%s] must be an object.', $parent),
            );
        }

        return $this->positiveInt(
            $container[$child] ?? null,
            sprintf('%s.%s', $parent, $child),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function optionalNestedPositiveInt(
        array $payload,
        string $parent,
        string $child,
        int $multiplier = 1,
    ): ?int {
        $container = $payload[$parent] ?? null;

        if (! is_array($container)) {
            return null;
        }

        $value = $this->optionalPositiveInt($container[$child] ?? null);

        return $value === null ? null : $value * $multiplier;
    }

    private function tomanToRial(mixed $value): string
    {
        if (
            ! is_int($value)
            && ! is_float($value)
            && ! (is_string($value) && is_numeric($value))
        ) {
            throw $this->unexpected('Liara price must be numeric.');
        }

        $numeric = (float) $value;

        if (! is_finite($numeric) || $numeric < 0) {
            throw $this->unexpected('Liara price must be a non-negative finite number.');
        }

        return (string) (int) round(
            $numeric * self::TOMAN_TO_RIAL,
            0,
            PHP_ROUND_HALF_UP,
        );
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw $this->unexpected('Liara date field is invalid.');
        }

        try {
            return new DateTimeImmutable(trim($value));
        } catch (Throwable $exception) {
            throw new CloudUnexpectedResponseException(
                message: 'Liara date field could not be parsed.',
                previous: $exception,
            );
        }
    }

    private function unexpected(string $message): CloudUnexpectedResponseException
    {
        return new CloudUnexpectedResponseException($message);
    }
}
