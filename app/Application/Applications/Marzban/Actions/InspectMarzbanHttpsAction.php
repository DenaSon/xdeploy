<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class InspectMarzbanHttpsAction
{
    private const string INSPECT_COMMAND = <<<'BASH'
env_file='/opt/marzban/.env'

if [ ! -r "$env_file" ]; then
    exit 2
fi

read_value() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$env_file" |
        tail -n 1 |
        tr -d '\r' |
        sed \
            -e "s/^[[:space:]]*//" \
            -e "s/[[:space:]]*$//" \
            -e "s/^[\"']//" \
            -e "s/[\"']$//"
}

cert_file="$(read_value 'UVICORN_SSL_CERTFILE')"
key_file="$(read_value 'UVICORN_SSL_KEYFILE')"
subscription_url="$(read_value 'XRAY_SUBSCRIPTION_URL_PREFIX')"

has_cert=0
has_key=0

[ -n "$cert_file" ] && has_cert=1
[ -n "$key_file" ] && has_key=1

if [ "$has_cert" -ne "$has_key" ]; then
    printf 'misconfigured\n%s' "$subscription_url"
    exit 0
fi

if [ "$has_cert" -eq 1 ]; then
    if [ ! -r "$cert_file" ] || [ ! -r "$key_file" ]; then
        printf 'misconfigured\n%s' "$subscription_url"
        exit 0
    fi
fi

case "$subscription_url" in
    https://*)
        if curl \
            --silent \
            --output /dev/null \
            --connect-timeout 3 \
            --max-time 5 \
            "$subscription_url"
        then
            printf 'managed_externally\n%s' "$subscription_url"
        else
            printf 'misconfigured\n%s' "$subscription_url"
        fi
        ;;

    *)
        if [ "$has_cert" -eq 1 ]; then
            printf 'misconfigured\n%s' "$subscription_url"
        else
            printf 'disabled\n'
        fi
        ;;
esac
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function execute(): MarzbanHttpsInfo
    {
        $result = $this->ssh->executeWithResult(
            command: self::INSPECT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        if (! $result->successful()) {
            return MarzbanHttpsInfo::unknown();
        }

        $lines = preg_split(
            pattern: '/\R/',
            subject: trim($result->output),
            limit: 2,
        );

        if ($lines === false) {
            return MarzbanHttpsInfo::unknown();
        }

        $state = MarzbanHttpsState::tryFrom(
            trim($lines[0] ?? ''),
        );

        if ($state === null) {
            return MarzbanHttpsInfo::unknown();
        }

        return new MarzbanHttpsInfo(
            state: $state,
            domain: $this->domainFromUrl($lines[1] ?? null),
        );
    }

    private function domainFromUrl(?string $url): ?string
    {
        $url = trim($url ?? '');

        if ($url === '') {
            return null;
        }

        $domain = parse_url($url, PHP_URL_HOST);

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return strtolower($domain);
    }
}
