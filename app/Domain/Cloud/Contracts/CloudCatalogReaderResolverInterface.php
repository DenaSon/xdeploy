<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\Enums\CloudProviderType;

interface CloudCatalogReaderResolverInterface
{
    public function resolve(
        CloudProviderType $provider,
    ): CloudCatalogReaderInterface;
}
