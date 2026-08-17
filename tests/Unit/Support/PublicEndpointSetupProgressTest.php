<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PublicEndpoint\PublicEndpointSetupProgress;
use PHPUnit\Framework\TestCase;

final class PublicEndpointSetupProgressTest extends TestCase
{
    public function test_initial_flow_starts_with_domain_and_keeps_later_steps_pending(): void
    {
        $progress = PublicEndpointSetupProgress::for(
            domain: '',
            dnsPreflight: null,
            serverPreflight: null,
            operationActive: false,
            activationSuccess: null,
            activationError: null,
            preflightError: null,
        );

        self::assertFalse($progress['preflight_ready']);
        self::assertFalse($progress['ready_for_activation']);
        self::assertSame([
            'domain' => 'current',
            'dns' => 'pending',
            'server' => 'pending',
            'https' => 'pending',
        ], $this->states($progress));
    }

    public function test_successful_preflight_marks_dns_and_server_complete_and_https_ready(): void
    {
        $progress = PublicEndpointSetupProgress::for(
            domain: 'automation.example.com',
            dnsPreflight: ['ready' => true],
            serverPreflight: ['ready' => true],
            operationActive: false,
            activationSuccess: null,
            activationError: null,
            preflightError: null,
        );

        self::assertTrue($progress['preflight_ready']);
        self::assertTrue($progress['ready_for_activation']);
        self::assertSame([
            'domain' => 'complete',
            'dns' => 'complete',
            'server' => 'complete',
            'https' => 'ready',
        ], $this->states($progress));
    }

    public function test_activation_running_and_success_have_distinct_https_states(): void
    {
        $running = PublicEndpointSetupProgress::for(
            domain: 'automation.example.com',
            dnsPreflight: ['ready' => true],
            serverPreflight: ['ready' => true],
            operationActive: true,
            activationSuccess: null,
            activationError: null,
            preflightError: null,
        );

        self::assertSame('running', $this->states($running)['https']);
        self::assertFalse($running['ready_for_activation']);

        $complete = PublicEndpointSetupProgress::for(
            domain: 'automation.example.com',
            dnsPreflight: ['ready' => true],
            serverPreflight: ['ready' => true],
            operationActive: false,
            activationSuccess: 'دامنه و HTTPS با موفقیت فعال شد.',
            activationError: null,
            preflightError: null,
        );

        self::assertTrue($complete['completed']);
        self::assertSame('complete', $this->states($complete)['https']);
        self::assertFalse($complete['ready_for_activation']);
    }

    public function test_failures_are_exposed_as_actionable_progress_states(): void
    {
        $preflightFailure = PublicEndpointSetupProgress::for(
            domain: 'automation.example.com',
            dnsPreflight: null,
            serverPreflight: null,
            operationActive: false,
            activationSuccess: null,
            activationError: null,
            preflightError: 'failed',
        );

        self::assertSame('error', $this->states($preflightFailure)['dns']);

        $activationFailure = PublicEndpointSetupProgress::for(
            domain: 'automation.example.com',
            dnsPreflight: ['ready' => true],
            serverPreflight: ['ready' => true],
            operationActive: false,
            activationSuccess: null,
            activationError: 'failed',
            preflightError: null,
        );

        self::assertTrue($activationFailure['preflight_ready']);
        self::assertFalse($activationFailure['ready_for_activation']);
        self::assertSame('error', $this->states($activationFailure)['https']);
    }

    /**
     * @param  array{steps:list<array{key:string,state:string}>}  $progress
     * @return array<string, string>
     */
    private function states(array $progress): array
    {
        $states = [];

        foreach ($progress['steps'] as $step) {
            $states[$step['key']] = $step['state'];
        }

        return $states;
    }
}
