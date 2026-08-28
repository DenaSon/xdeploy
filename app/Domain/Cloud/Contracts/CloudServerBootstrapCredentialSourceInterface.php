<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerBootstrapCredentialData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;

interface CloudServerBootstrapCredentialSourceInterface
{
    public function bootstrapCredential(
        CreateCloudServerData $request,
        CreatedCloudServerData $server,
    ): CloudServerBootstrapCredentialData;
}
