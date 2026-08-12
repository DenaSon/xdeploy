<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\DTOs;

final readonly class PublicEndpointDnsPreflightResult
{
    /**
     * @param  list<string>  $resolvedIpv4Addresses
     * @param  list<string>  $resolvedIpv6Addresses
     */
    public function __construct(
        public string $domain,
        public string $serverIpv4Address,
        public array $resolvedIpv4Addresses,
        public array $resolvedIpv6Addresses,
    ) {}

    public function ipv4MatchesServer(): bool
    {
        return $this->resolvedIpv4Addresses === [$this->serverIpv4Address];
    }

    public function hasIncompatibleIpv6(): bool
    {
        return $this->resolvedIpv6Addresses !== [];
    }

    public function ready(): bool
    {
        return $this->ipv4MatchesServer() && ! $this->hasIncompatibleIpv6();
    }

    /**
     * @return array{
     *   domain: string,
     *   server_ipv4_address: string,
     *   resolved_ipv4_addresses: list<string>,
     *   resolved_ipv6_addresses: list<string>,
     *   ipv4_matches_server: bool,
     *   has_incompatible_ipv6: bool,
     *   ready: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'server_ipv4_address' => $this->serverIpv4Address,
            'resolved_ipv4_addresses' => $this->resolvedIpv4Addresses,
            'resolved_ipv6_addresses' => $this->resolvedIpv6Addresses,
            'ipv4_matches_server' => $this->ipv4MatchesServer(),
            'has_incompatible_ipv6' => $this->hasIncompatibleIpv6(),
            'ready' => $this->ready(),
        ];
    }
}
