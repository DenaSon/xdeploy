<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\Termination\CloudServerTerminationPolicyResolver;
use App\Application\Cloud\Servers\Termination\CloudServerTerminationState;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class CloudServerTerminationPolicyResolverTest extends TestCase
{
    public function test_parspack_is_ready_for_immediate_provider_delete(): void
    {
        $server = new Server();
        $server->cloud_provider = CloudProviderType::ParsPack;

        $decision = (new CloudServerTerminationPolicyResolver())
            ->advance($server);

        $this->assertSame(
            CloudServerTerminationState::ReadyForDelete,
            $decision->state,
        );
        $this->assertTrue($decision->readyForDelete());
    }
}
