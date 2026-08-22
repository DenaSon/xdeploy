<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderType;
use Closure;

interface CloudProviderHealthStoreInterface
{
    public function get(
        CloudProviderType $provider,
    ): ?CloudProviderHealthSnapshot;

    /**
     * @param  Closure(?CloudProviderHealthSnapshot): CloudProviderHealthSnapshot  $mutator
     */
    public function update(
        CloudProviderType $provider,
        Closure $mutator,
    ): CloudProviderHealthSnapshot;
}
