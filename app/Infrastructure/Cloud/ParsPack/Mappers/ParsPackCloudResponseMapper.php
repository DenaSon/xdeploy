<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ParsPack\Mappers;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
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

final readonly class ParsPackCloudResponseMapper
{
    private const string CURRENCY = 'IRR';
    private const string DEFAULT_USERNAME = 'root';

    /** @return list<CloudRegionData> */
    public function mapRegions(array $response): array
    {
        $regions = [];

        foreach ($this->dataList($response, 'region') as $region) {
            if (! is_array($region)) {
                throw $this->unexpected('ParsPack region catalog contains an invalid entry.');
            }

            $slug = $this->requiredString($region, 'slug');
            $name = $this->optionalString($region['name'] ?? null) ?? $slug;
            $available = $this->optionalBool($region['available'] ?? null) ?? true;

            $regions[] = new CloudRegionData(
                id: $slug,
                displayName: $name,
                country: $this->countryForRegion($slug),
                city: $name,
                dataCenter: null,
                canCreateServers: $available,
                isVisible: $available,
                supportsVolumeBacked: false,
            );
        }

        return $regions;
    }

    /** @return list<CloudSizeData> */
    public function mapSizes(array $response, string $region): array
    {
        $region = trim($region);
        $sizes = [];

        foreach ($this->dataList($response, 'size') as $size) {
            if (! is_array($size)) {
                throw $this->unexpected('ParsPack size catalog contains an invalid entry.');
            }

            if (($this->optionalBool($size['available'] ?? null) ?? true) !== true) {
                continue;
            }

            if (! $this->sizeSupportsRegion($size, $region)) {
                continue;
            }

            $slug = $this->requiredString($size, 'slug');

            $sizes[] = new CloudSizeData(
                id: $slug,
                name: $this->optionalString($size['description'] ?? null) ?? $slug,
                regionId: $region,
                vCpu: $this->positiveInt($size['vcpus'] ?? null, 'vcpus'),
                memoryMiB: $this->positiveInt($size['memory'] ?? null, 'memory'),
                diskGiB: $this->positiveInt($size['disk'] ?? null, 'disk'),
                category: 'parspack',
                hourlyPrice: new CloudPriceData(
                    amount: $this->decimalString($size['price_hourly'] ?? null, 'price_hourly'),
                    currencyCode: self::CURRENCY,
                    billingPeriod: CloudBillingPeriod::Hourly,
                ),
                monthlyPrice: new CloudPriceData(
                    amount: $this->decimalString($size['price_monthly'] ?? null, 'price_monthly'),
                    currencyCode: self::CURRENCY,
                    billingPeriod: CloudBillingPeriod::Monthly,
                ),
            );
        }

        return $sizes;
    }

    /** @return list<CloudImageData> */
    public function mapImages(array $response, string $region): array
    {
        $region = trim($region);
        $images = [];

        foreach ($this->dataList($response, 'image') as $image) {
            if (! is_array($image)) {
                throw $this->unexpected('ParsPack image catalog contains an invalid entry.');
            }

            if (($this->optionalBool($image['public'] ?? null) ?? false) !== true) {
                continue;
            }

            if (strtolower($this->requiredString($image, 'status')) !== 'available') {
                continue;
            }

            if (strtolower($this->requiredString($image, 'type')) !== 'base') {
                continue;
            }

            if (! $this->imageSupportsRegion($image, $region)) {
                continue;
            }

            $slug = $this->requiredString($image, 'slug');
            [$distribution, $version] = $this->distributionAndVersion(
                slug: $slug,
                name: $this->optionalString($image['name'] ?? null),
            );

            $images[] = new CloudImageData(
                id: $slug,
                name: $this->optionalString($image['name'] ?? null) ?? $slug,
                regionId: $region,
                distribution: $distribution,
                version: $version,
                architecture: $this->imageArchitecture($image, $slug),
                minDiskGiB: $this->optionalPositiveInt(
                    $image['min_disk'] ?? $image['min_disk_gib'] ?? null,
                ),
                minMemoryMiB: $this->optionalPositiveInt(
                    $image['min_memory'] ?? $image['min_memory_mib'] ?? null,
                ),
                supportsSshKey: true,
                supportsPassword: true,
            );
        }

        return $images;
    }

    public function mapCreatedServer(array $response): CreatedCloudServerData
    {
        $id = $this->requiredString($response, 'id');
        $this->assertServerId($id);

        return new CreatedCloudServerData(
            id: $id,
            name: $this->requiredString($response, 'name'),
            regionId: $this->embeddedSlug($response['region'] ?? null, 'region'),
            status: $this->mapStatus($this->requiredString($response, 'status')),
            username: self::DEFAULT_USERNAME,
            createdAt: $this->dateTime($response['created_at'] ?? null),
            generatedPassword: null,
        );
    }

    public function mapServer(array $response, string $region): CloudServerData
    {
        $id = $this->requiredString($response, 'id');
        $this->assertServerId($id);
        $status = strtolower($this->requiredString($response, 'status'));
        $size = $this->embeddedObject($response['size'] ?? null);
        $image = $this->embeddedObject($response['image'] ?? null);
        $vpcUuid = $this->optionalScalarString($response['vpc_uuid'] ?? null);

        return new CloudServerData(
            id: $id,
            name: $this->requiredString($response, 'name'),
            regionId: $this->embeddedSlugOrFallback(
                $response['region'] ?? null,
                'region',
                $region,
            ),
            status: $this->mapStatus($status),
            username: self::DEFAULT_USERNAME,
            sizeId: $size === null ? null : $this->optionalString($size['slug'] ?? null),
            imageId: $image === null ? null : $this->optionalString($image['slug'] ?? null),
            createdAt: $this->dateTime($response['created_at'] ?? null),
            addresses: $this->mapAddresses($response['networks'] ?? []),
            networkIds: $vpcUuid === null ? [] : [$vpcUuid],
            securityGroupIds: [],
            volumeBacked: false,
            highAvailability: false,
            sizeName: $size === null ? null : $this->optionalString($size['slug'] ?? null),
            vCpu: $this->optionalPositiveInt(
                $response['vcpus'] ?? ($size['vcpus'] ?? null),
            ),
            memoryMiB: $this->optionalPositiveInt(
                $response['memory'] ?? ($size['memory'] ?? null),
            ),
            diskGiB: $this->optionalPositiveInt(
                $response['disk'] ?? ($size['disk'] ?? null),
            ),
            taskState: $this->actionStatus($response['action'] ?? null),
            providerError: null,
            powerState: $this->mapPowerState($status),
            generatedPassword: null,
        );
    }

    /** @return list<CloudServerData> */
    public function mapServerInventory(array $response): array
    {
        $servers = [];

        foreach ($this->dataList($response, 'VM') as $server) {
            if (! is_array($server)) {
                throw $this->unexpected('ParsPack VM inventory contains an invalid entry.');
            }

            $region = $this->embeddedSlug($server['region'] ?? null, 'region');
            $servers[] = $this->mapServer($server, $region);
        }

        return $servers;
    }

    /** @return list<CloudServerAddressData> */
    private function mapAddresses(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->unexpected('ParsPack VM network list is invalid.');
        }

        $addresses = [];

        foreach ($value as $network) {
            if (! is_array($network)) {
                throw $this->unexpected('ParsPack VM network entry is invalid.');
            }

            $address = $this->optionalString($network['ip'] ?? null)
                ?? $this->optionalString($network['address'] ?? null);

            if ($address === null) {
                throw $this->unexpected('ParsPack VM network entry has no address.');
            }

            $version = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? CloudIpVersion::IPv4
                : (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                    ? CloudIpVersion::IPv6
                    : null);

            if (! $version instanceof CloudIpVersion) {
                throw $this->unexpected('ParsPack VM network address is invalid.');
            }

            $type = strtolower($this->optionalString($network['type'] ?? null) ?? 'unknown');
            $isPublic = $type === 'public';

            $addresses[] = new CloudServerAddressData(
                address: $address,
                version: $version,
                isPublic: $isPublic,
                isVpc: ! $isPublic,
                type: $type,
            );
        }

        return $addresses;
    }

    private function mapStatus(string $status): CloudServerStatus
    {
        return match (strtolower(trim($status))) {
            'active', 'off' => CloudServerStatus::Active,
            'new', 'creating', 'pending', 'provisioning', 'rebuilding', 'resizing' => CloudServerStatus::Provisioning,
            'failed', 'error' => CloudServerStatus::Failed,
            default => CloudServerStatus::Unknown,
        };
    }

    private function mapPowerState(string $status): CloudServerPowerState
    {
        return match (strtolower(trim($status))) {
            'active' => CloudServerPowerState::Running,
            'off' => CloudServerPowerState::Stopped,
            'new', 'creating', 'pending', 'provisioning', 'rebuilding', 'resizing' => CloudServerPowerState::Transitioning,
            'failed', 'error' => CloudServerPowerState::Error,
            default => CloudServerPowerState::Unknown,
        };
    }

    /** @return list<mixed> */
    private function dataList(array $response, string $resource): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        $data = $response['data'] ?? null;

        if (! is_array($data) || ! array_is_list($data)) {
            throw $this->unexpected(
                sprintf('ParsPack %s payload does not contain a data list.', $resource),
            );
        }

        return $data;
    }

    /** @param array<string, mixed> $size */
    private function sizeSupportsRegion(array $size, string $region): bool
    {
        $regions = $size['regions'] ?? null;

        if (! is_array($regions) || ! array_is_list($regions)) {
            throw $this->unexpected('ParsPack size regions payload is invalid.');
        }

        foreach ($regions as $candidate) {
            $slug = is_string($candidate)
                ? trim($candidate)
                : (is_array($candidate)
                    ? $this->optionalString($candidate['slug'] ?? null)
                    : null);

            if ($slug === $region) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $image */
    private function imageSupportsRegion(array $image, string $region): bool
    {
        $regions = $image['regions'] ?? [];

        if (! is_array($regions) || ! array_is_list($regions)) {
            throw $this->unexpected('ParsPack image regions payload is invalid.');
        }

        if ($regions === []) {
            return true;
        }

        foreach ($regions as $candidate) {
            $slug = is_string($candidate)
                ? trim($candidate)
                : (is_array($candidate)
                    ? $this->optionalString($candidate['slug'] ?? null)
                    : null);

            if ($slug === $region) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0:string,1:string} */
    private function distributionAndVersion(string $slug, ?string $name): array
    {
        $haystack = strtolower(trim($slug.' '.($name ?? '')));

        if (preg_match('/ubuntu[^0-9]*([0-9]{2})(?:[. _-]?([0-9]{2}))?/i', $haystack, $matches) === 1) {
            $major = $matches[1];
            $minor = $matches[2] ?? '04';

            return ['ubuntu', sprintf('%s.%s', $major, $minor)];
        }

        if (preg_match('/debian[^0-9]*([0-9]{1,2})/i', $haystack, $matches) === 1) {
            return ['debian', $matches[1]];
        }

        if (preg_match('/alma(?:linux)?[^0-9]*([0-9]+)(?:[. _-]([0-9]+))?/i', $haystack, $matches) === 1) {
            $version = isset($matches[2])
                ? sprintf('%s.%s', $matches[1], $matches[2])
                : $matches[1];

            return ['almalinux', $version];
        }

        throw $this->unexpected(
            sprintf('ParsPack base image [%s] distribution/version could not be identified.', $slug),
        );
    }

    /** @param array<string, mixed> $image */
    private function imageArchitecture(array $image, string $slug): ?string
    {
        $architecture = $this->optionalString($image['architecture'] ?? null);

        if ($architecture !== null) {
            return strtolower($architecture);
        }

        $name = strtolower((string) ($image['name'] ?? ''));
        $haystack = strtolower($slug.' '.$name);

        if (str_contains($haystack, 'x64') || str_contains($haystack, 'amd64')) {
            return 'x86_64';
        }

        if (str_contains($haystack, 'arm64') || str_contains($haystack, 'aarch64')) {
            return 'arm64';
        }

        return null;
    }

    private function embeddedSlug(mixed $value, string $field): string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_array($value)) {
            return $this->requiredString($value, 'slug');
        }

        throw $this->unexpected(
            sprintf('ParsPack response field [%s] is invalid.', $field),
        );
    }

    private function embeddedSlugOrFallback(
        mixed $value,
        string $field,
        string $fallback,
    ): string {
        if ($value === null) {
            return trim($fallback);
        }

        return $this->embeddedSlug($value, $field);
    }

    /** @return array<string, mixed>|null */
    private function embeddedObject(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        return $value;
    }

    private function actionStatus(mixed $action): ?string
    {
        if (! is_array($action)) {
            return null;
        }

        return $this->optionalString($action['status'] ?? null);
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $field): string
    {
        $value = $this->optionalString($payload[$field] ?? null);

        if ($value === null) {
            throw $this->unexpected(
                sprintf('ParsPack response is missing required field [%s].', $field),
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

    private function optionalScalarString(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->optionalString($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return null;
    }

    private function optionalBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $int = $this->optionalPositiveInt($value);

        if ($int === null) {
            throw $this->unexpected(
                sprintf('ParsPack field [%s] must be a positive integer.', $field),
            );
        }

        return $int;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private function decimalString(mixed $value, string $field): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw $this->unexpected(
                sprintf('ParsPack field [%s] must be numeric.', $field),
            );
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        } else {
            $value = trim((string) $value);
        }

        if ($value === '' || preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $value) !== 1) {
            throw $this->unexpected(
                sprintf('ParsPack field [%s] contains an invalid numeric value.', $field),
            );
        }

        return $value;
    }

    private function dateTime(mixed $value): ?DateTimeImmutable
    {
        $value = $this->optionalString($value);

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw $this->unexpected(
                'ParsPack response contains an invalid date-time.',
                $exception,
            );
        }
    }

    private function assertServerId(string $id): void
    {
        if (preg_match('/\A[a-f0-9]{4}(?:-[a-f0-9]{4}){3}\z/i', $id) !== 1) {
            throw $this->unexpected('ParsPack response contains an invalid VM identifier.');
        }
    }

    private function countryForRegion(string $slug): ?string
    {
        return match (true) {
            str_starts_with($slug, 'tehran') => 'IR',
            $slug === 'frankfurt' => 'DE',
            $slug === 'amsterdam' => 'NL',
            $slug === 'london1' => 'GB',
            $slug === 'istanbul' => 'TR',
            $slug === 'paris' => 'FR',
            $slug === 'toronto2' => 'CA',
            $slug === 'stockholm' => 'SE',
            default => null,
        };
    }

    private function unexpected(
        string $message,
        ?Throwable $previous = null,
    ): CloudUnexpectedResponseException {
        return new CloudUnexpectedResponseException(
            message: $message,
            previous: $previous,
        );
    }
}
