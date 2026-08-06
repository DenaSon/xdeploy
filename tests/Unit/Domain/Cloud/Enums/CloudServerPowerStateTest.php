<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\Enums;

use App\Domain\Cloud\Enums\CloudServerPowerState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CloudServerPowerStateTest extends TestCase
{
    public function test_it_defines_expected_values(): void
    {
        $this->assertSame(
            'running',
            CloudServerPowerState::Running->value,
        );

        $this->assertSame(
            'stopped',
            CloudServerPowerState::Stopped->value,
        );

        $this->assertSame(
            'transitioning',
            CloudServerPowerState::Transitioning->value,
        );

        $this->assertSame(
            'error',
            CloudServerPowerState::Error->value,
        );

        $this->assertSame(
            'unknown',
            CloudServerPowerState::Unknown->value,
        );
    }

    #[DataProvider('runningStateProvider')]
    public function test_it_identifies_running_state(
        CloudServerPowerState $state,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isRunning(),
        );
    }

    /**
     * @return array<string, array{
     *     CloudServerPowerState,
     *     bool
     * }>
     */
    public static function runningStateProvider(): array
    {
        return [
            'running' => [
                CloudServerPowerState::Running,
                true,
            ],

            'stopped' => [
                CloudServerPowerState::Stopped,
                false,
            ],

            'transitioning' => [
                CloudServerPowerState::Transitioning,
                false,
            ],

            'error' => [
                CloudServerPowerState::Error,
                false,
            ],

            'unknown' => [
                CloudServerPowerState::Unknown,
                false,
            ],
        ];
    }

    #[DataProvider('stoppedStateProvider')]
    public function test_it_identifies_stopped_state(
        CloudServerPowerState $state,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isStopped(),
        );
    }

    /**
     * @return array<string, array{
     *     CloudServerPowerState,
     *     bool
     * }>
     */
    public static function stoppedStateProvider(): array
    {
        return [
            'running' => [
                CloudServerPowerState::Running,
                false,
            ],

            'stopped' => [
                CloudServerPowerState::Stopped,
                true,
            ],

            'transitioning' => [
                CloudServerPowerState::Transitioning,
                false,
            ],

            'error' => [
                CloudServerPowerState::Error,
                false,
            ],

            'unknown' => [
                CloudServerPowerState::Unknown,
                false,
            ],
        ];
    }

    #[DataProvider('transitioningStateProvider')]
    public function test_it_identifies_transitioning_state(
        CloudServerPowerState $state,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isTransitioning(),
        );
    }

    /**
     * @return array<string, array{
     *     CloudServerPowerState,
     *     bool
     * }>
     */
    public static function transitioningStateProvider(): array
    {
        return [
            'running' => [
                CloudServerPowerState::Running,
                false,
            ],

            'stopped' => [
                CloudServerPowerState::Stopped,
                false,
            ],

            'transitioning' => [
                CloudServerPowerState::Transitioning,
                true,
            ],

            'error' => [
                CloudServerPowerState::Error,
                false,
            ],

            'unknown' => [
                CloudServerPowerState::Unknown,
                false,
            ],
        ];
    }

    #[DataProvider('errorStateProvider')]
    public function test_it_identifies_error_state(
        CloudServerPowerState $state,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isError(),
        );
    }

    /**
     * @return array<string, array{
     *     CloudServerPowerState,
     *     bool
     * }>
     */
    public static function errorStateProvider(): array
    {
        return [
            'running' => [
                CloudServerPowerState::Running,
                false,
            ],

            'stopped' => [
                CloudServerPowerState::Stopped,
                false,
            ],

            'transitioning' => [
                CloudServerPowerState::Transitioning,
                false,
            ],

            'error' => [
                CloudServerPowerState::Error,
                true,
            ],

            'unknown' => [
                CloudServerPowerState::Unknown,
                false,
            ],
        ];
    }

    #[DataProvider('knownStateProvider')]
    public function test_it_identifies_known_states(
        CloudServerPowerState $state,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $state->isKnown(),
        );
    }

    /**
     * @return array<string, array{
     *     CloudServerPowerState,
     *     bool
     * }>
     */
    public static function knownStateProvider(): array
    {
        return [
            'running' => [
                CloudServerPowerState::Running,
                true,
            ],

            'stopped' => [
                CloudServerPowerState::Stopped,
                true,
            ],

            'transitioning' => [
                CloudServerPowerState::Transitioning,
                true,
            ],

            'error' => [
                CloudServerPowerState::Error,
                true,
            ],

            'unknown' => [
                CloudServerPowerState::Unknown,
                false,
            ],
        ];
    }
}
