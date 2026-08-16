<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudSshKeyData;

interface CloudSshKeyCatalogInterface
{
    /**
     * @return list<CloudSshKeyData>
     */
    public function listSshKeys(
        string $region,
    ): array;
}
