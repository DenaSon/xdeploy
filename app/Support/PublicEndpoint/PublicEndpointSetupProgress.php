<?php

declare(strict_types=1);

namespace App\Support\PublicEndpoint;

final class PublicEndpointSetupProgress
{
    /**
     * @param  array<string, mixed>|null  $dnsPreflight
     * @param  array<string, mixed>|null  $serverPreflight
     * @return array{
     *     preflight_ready: bool,
     *     ready_for_activation: bool,
     *     completed: bool,
     *     steps: list<array{key:string,label:string,icon:string,state:string}>
     * }
     */
    public static function for(
        string $domain,
        ?array $dnsPreflight,
        ?array $serverPreflight,
        bool $operationActive,
        ?string $activationSuccess,
        ?string $activationError,
        ?string $preflightError,
    ): array {
        $domainReady = trim($domain) !== '';
        $dnsReady = ($dnsPreflight['ready'] ?? false) === true;
        $serverReady = ($serverPreflight['ready'] ?? false) === true;
        $completed = $activationSuccess !== null;
        $preflightReady = $dnsReady && $serverReady;
        $readyForActivation = $preflightReady
            && ! $completed
            && ! $operationActive
            && $activationError === null;

        return [
            'preflight_ready' => $preflightReady,
            'ready_for_activation' => $readyForActivation,
            'completed' => $completed,
            'steps' => [
                [
                    'key' => 'domain',
                    'label' => 'دامنه',
                    'icon' => 'lucide.globe-2',
                    'state' => $domainReady ? 'complete' : 'current',
                ],
                [
                    'key' => 'dns',
                    'label' => 'DNS',
                    'icon' => 'lucide.network',
                    'state' => self::preflightStepState(
                        known: $dnsPreflight !== null,
                        ready: $dnsReady,
                        previousReady: $domainReady,
                        failed: $preflightError !== null,
                    ),
                ],
                [
                    'key' => 'server',
                    'label' => 'سرور',
                    'icon' => 'lucide.server',
                    'state' => self::preflightStepState(
                        known: $serverPreflight !== null,
                        ready: $serverReady,
                        previousReady: $dnsReady,
                        failed: false,
                    ),
                ],
                [
                    'key' => 'https',
                    'label' => 'HTTPS',
                    'icon' => 'lucide.lock-keyhole',
                    'state' => match (true) {
                        $completed => 'complete',
                        $operationActive => 'running',
                        $activationError !== null => 'error',
                        $preflightReady => 'ready',
                        default => 'pending',
                    },
                ],
            ],
        ];
    }

    private static function preflightStepState(
        bool $known,
        bool $ready,
        bool $previousReady,
        bool $failed,
    ): string {
        if ($ready) {
            return 'complete';
        }

        if ($known || $failed) {
            return 'error';
        }

        return $previousReady ? 'current' : 'pending';
    }
}
