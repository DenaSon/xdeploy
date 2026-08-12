<?php

declare(strict_types=1);

namespace App\Infrastructure\PublicEndpoint;

use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class SshPublicEndpointDnsPreflight
{
    private const string COMMAND = <<<'BASH'
domain=%s
server_ipv4=%s

if [ -z "$server_ipv4" ]; then
    server_ipv4="$(
        curl --ipv4 --fail --silent --show-error \
            --connect-timeout 4 --max-time 8 \
            'https://api.ipify.org'
    )" || exit 41
fi

server_ipv4="$(printf '%%s' "$server_ipv4" | tr -d '\r\n')"
[ -n "$server_ipv4" ] || exit 41
command -v getent >/dev/null 2>&1 || exit 42

printf 'xdeploy_dns_preflight=1\n'
printf 'server_ipv4=%%s\n' "$server_ipv4"

getent ahostsv4 "$domain" 2>/dev/null |
    awk '{print $1}' | sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'a=%%s\n' "$address"
    done

getent ahostsv6 "$domain" 2>/dev/null |
    awk 'tolower($1) !~ /^::ffff:/ {print $1}' | sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'aaaa=%%s\n' "$address"
    done
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function check(
        PublicEndpointDomain $domain,
        ?string $knownServerAddress = null,
    ): PublicEndpointDnsPreflightResult {
        $knownIpv4 = filter_var(
            $knownServerAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        $result = $this->ssh->executeWithResult(
            command: sprintf(
                self::COMMAND,
                escapeshellarg($domain->value),
                escapeshellarg(is_string($knownIpv4) ? $knownIpv4 : ''),
            ),
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $marker = false;
        $serverIpv4 = null;
        $resolvedIpv4 = [];
        $resolvedIpv6 = [];

        foreach (preg_split('/\R/', trim($result->output)) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', trim($line), 2), 2, '');

            if ($key === 'xdeploy_dns_preflight' && $value === '1') {
                $marker = true;

                continue;
            }

            if ($key === 'server_ipv4' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $serverIpv4 = $value;

                continue;
            }

            if ($key === 'a' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $resolvedIpv4[] = $value;

                continue;
            }

            if ($key === 'aaaa' && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $resolvedIpv6[] = $value;
            }
        }

        if (! $marker || $serverIpv4 === null) {
            throw PublicEndpointOperationException::preflightFailed();
        }

        $resolvedIpv4 = array_values(array_unique($resolvedIpv4));
        $resolvedIpv6 = array_values(array_unique($resolvedIpv6));
        sort($resolvedIpv4);
        sort($resolvedIpv6);

        return new PublicEndpointDnsPreflightResult(
            domain: $domain->value,
            serverIpv4Address: $serverIpv4,
            resolvedIpv4Addresses: $resolvedIpv4,
            resolvedIpv6Addresses: $resolvedIpv6,
        );
    }
}
