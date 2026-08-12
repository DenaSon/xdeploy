<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Platform\Caddy;

use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsDisabler;
use App\Infrastructure\Application\Marzban\SshMarzbanHttpsGateway;
use App\Infrastructure\Application\N8n\PublicEndpoint\SshN8nPublicEndpointGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CaddyMutationConflictMappingTest extends TestCase
{
    #[DataProvider('marzbanMappers')]
    public function test_marzban_maps_caddy_ownership_conflicts_to_existing_configuration(
        string $class,
    ): void {
        $mapped = $this->invokeMapper($class);

        self::assertSame(
            MarzbanHttpsApplyFailure::ExistingConfiguration,
            $mapped->failure,
        );
    }

    public function test_n8n_maps_caddy_ownership_conflicts_to_existing_configuration(): void
    {
        $mapped = $this->invokeMapper(
            SshN8nPublicEndpointGateway::class,
        );

        self::assertSame(
            PublicEndpointOperationFailure::ExistingConfiguration,
            $mapped->failure,
        );
    }

    /** @return array<string, array{class-string}> */
    public static function marzbanMappers(): array
    {
        return [
            'enable gateway' => [SshMarzbanHttpsGateway::class],
            'disable gateway' => [SshMarzbanHttpsDisabler::class],
        ];
    }

    private function invokeMapper(string $class): object
    {
        $reflection = new ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('mapCaddyMutationException');

        return $method->invoke(
            $instance,
            new CaddySiteMutationException(
                CaddySiteMutationFailure::Conflict,
            ),
        );
    }
}
