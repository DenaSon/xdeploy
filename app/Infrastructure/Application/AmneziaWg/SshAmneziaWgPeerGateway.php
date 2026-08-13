<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\AmneziaWg;

use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerGateway;
use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerProvisioningResult;
use App\Domain\Application\AmneziaWg\Peer\DTOs\AmneziaWgPeerRuntimeState;
use App\Domain\Application\AmneziaWg\Peer\Exceptions\AmneziaWgPeerManagementException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;
use Throwable;

final readonly class SshAmneziaWgPeerGateway implements AmneziaWgPeerGateway
{
    private const string CREATE_COMMAND = <<<'BASH'
set -Eeuo pipefail

readonly config='/opt/xdeploy/apps/amneziawg/data/awg0.conf'
readonly env_file='/opt/xdeploy/apps/amneziawg/.env'
readonly server_public_key_file='/opt/xdeploy/apps/amneziawg/data/wireguard_server_public_key.key'
readonly container='amnezia-awg2'

peer_ip={{ peer_ip }}
endpoint_host={{ endpoint_host }}

[[ -s "$config" ]] || exit 20
[[ -s "$env_file" ]] || exit 21
[[ -s "$server_public_key_file" ]] || exit 22

docker inspect --format '{{.State.Running}}' "$container" 2>/dev/null | grep -Fxq 'true' || exit 23

read_config_value() {
    local key="$1"

    awk -F= -v wanted="$key" '
        function trim(value) {
            sub(/^[[:space:]]+/, "", value)
            sub(/[[:space:]]+$/, "", value)
            return value
        }

        {
            name = trim($1)
            if (name == wanted) {
                print trim(substr($0, index($0, "=") + 1))
                exit
            }
        }
    ' "$config"
}

awg_port="$(awk -F= '$1 == "AWG_PORT" { gsub(/[[:space:]]/, "", $2); print $2; exit }' "$env_file")"
[[ "$awg_port" =~ ^[0-9]+$ ]] || exit 24

if grep -Fq "AllowedIPs = ${peer_ip}/32" "$config"; then
    exit 25
fi

server_public_key="$(tr -d '\r\n' < "$server_public_key_file")"
[[ -n "$server_public_key" ]] || exit 26

client_private_key="$(docker exec "$container" awg genkey)"
[[ -n "$client_private_key" ]] || exit 27

client_public_key="$(printf '%s\n' "$client_private_key" | docker exec -i "$container" awg pubkey)"
[[ -n "$client_public_key" ]] || exit 28

client_psk="$(docker exec "$container" awg genpsk)"
[[ -n "$client_psk" ]] || exit 29

backup="$(mktemp)"
cp -a "$config" "$backup"

cleanup() {
    rm -f "$backup"
}

restore_runtime() {
    cp -a "$backup" "$config"
    docker exec "$container" bash -lc \
        'awg syncconf awg0 <(awg-quick strip /opt/amnezia/awg/awg0.conf)' \
        >/dev/null 2>&1 || true
}

trap cleanup EXIT

cat >> "$config" <<PEER

[Peer]
PublicKey = ${client_public_key}
PresharedKey = ${client_psk}
AllowedIPs = ${peer_ip}/32
PEER

if ! docker exec "$container" bash -lc \
    'awg syncconf awg0 <(awg-quick strip /opt/amnezia/awg/awg0.conf)'; then
    restore_runtime
    exit 30
fi

if ! docker exec "$container" awg show awg0 peers \
    | grep -Fxq "$client_public_key"; then
    restore_runtime
    exit 31
fi

jc="$(read_config_value 'Jc')"
jmin="$(read_config_value 'Jmin')"
jmax="$(read_config_value 'Jmax')"
s1="$(read_config_value 'S1')"
s2="$(read_config_value 'S2')"
s3="$(read_config_value 'S3')"
s4="$(read_config_value 'S4')"
h1="$(read_config_value 'H1')"
h2="$(read_config_value 'H2')"
h3="$(read_config_value 'H3')"
h4="$(read_config_value 'H4')"

client_config="$(cat <<CONF
[Interface]
PrivateKey = ${client_private_key}
Address = ${peer_ip}/32
Jc = ${jc}
Jmin = ${jmin}
Jmax = ${jmax}
S1 = ${s1}
S2 = ${s2}
S3 = ${s3}
S4 = ${s4}
H1 = ${h1}
H2 = ${h2}
H3 = ${h3}
H4 = ${h4}

[Peer]
PublicKey = ${server_public_key}
PresharedKey = ${client_psk}
AllowedIPs = 0.0.0.0/0, ::/0
Endpoint = ${endpoint_host}:${awg_port}
PersistentKeepalive = 25
CONF
)"

printf 'XDEPLOY_PUBLIC_KEY=%s\n' "$client_public_key"
printf 'XDEPLOY_CLIENT_CONFIG_B64=%s\n' "$(printf '%s' "$client_config" | base64 -w 0)"
BASH;

    private const string REMOVE_COMMAND = <<<'BASH'
set -Eeuo pipefail

readonly config='/opt/xdeploy/apps/amneziawg/data/awg0.conf'
readonly container='amnezia-awg2'

public_key={{ public_key }}

[[ -s "$config" ]] || exit 40

docker inspect --format '{{.State.Running}}' "$container" 2>/dev/null | grep -Fxq 'true' || exit 41

if ! docker exec "$container" awg show awg0 peers | grep -Fxq "$public_key"; then
    exit 0
fi

backup="$(mktemp)"
candidate="$(mktemp)"
cp -a "$config" "$backup"

cleanup() {
    rm -f "$backup" "$candidate"
}

restore_runtime() {
    cp -a "$backup" "$config"
    docker exec "$container" bash -lc \
        'awg syncconf awg0 <(awg-quick strip /opt/amnezia/awg/awg0.conf)' \
        >/dev/null 2>&1 || true
}

trap cleanup EXIT

awk -v target="$public_key" '
    BEGIN {
        RS = ""
        ORS = "\n\n"
    }

    function trim(value) {
        sub(/^[[:space:]]+/, "", value)
        sub(/[[:space:]]+$/, "", value)
        return value
    }

    {
        remove = 0

        if ($0 ~ /^\[Peer\]/) {
            count = split($0, lines, "\n")

            for (i = 1; i <= count; i++) {
                separator = index(lines[i], "=")

                if (separator == 0) {
                    continue
                }

                key = trim(substr(lines[i], 1, separator - 1))
                value = trim(substr(lines[i], separator + 1))

                if (key == "PublicKey" && value == target) {
                    remove = 1
                    break
                }
            }
        }

        if (! remove) {
            print $0
        }
    }
' "$config" > "$candidate"

install -m 0600 "$candidate" "$config"

if ! docker exec "$container" bash -lc \
    'awg syncconf awg0 <(awg-quick strip /opt/amnezia/awg/awg0.conf)'; then
    restore_runtime
    exit 42
fi

if docker exec "$container" awg show awg0 peers | grep -Fxq "$public_key"; then
    restore_runtime
    exit 43
fi
BASH;

    private const string RUNTIME_COMMAND = <<<'BASH'
set -Eeuo pipefail

readonly container='amnezia-awg2'

docker inspect --format '{{.State.Running}}' "$container" 2>/dev/null | grep -Fxq 'true' || exit 50

docker exec "$container" awg show awg0 latest-handshakes
printf '%s\n' '__XDEPLOY_TRANSFER__'
docker exec "$container" awg show awg0 transfer
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function createPeer(
        string $ipAddress,
        string $endpointHost,
    ): AmneziaWgPeerProvisioningResult {
        try {
            $result = $this->privileged->executeWithResult(
                command: $this->creationCommand(
                    $ipAddress,
                    $endpointHost,
                ),
                timeout: SSHTimeout::NORMAL,
                sensitive: true,
            );
        } catch (Throwable) {
            throw AmneziaWgPeerManagementException::creationFailed();
        }

        if (! $result->successful()) {
            throw AmneziaWgPeerManagementException::creationFailed();
        }

        return $this->parseCreationOutput(
            $result->output,
        );
    }

    public function removePeer(
        string $publicKey,
    ): void {
        try {
            $result = $this->privileged->executeWithResult(
                command: strtr(
                    self::REMOVE_COMMAND,
                    [
                        '{{ public_key }}' => $this->shellArgument($publicKey),
                    ],
                ),
                timeout: SSHTimeout::NORMAL,
            );
        } catch (Throwable) {
            throw AmneziaWgPeerManagementException::removalFailed();
        }

        if (! $result->successful()) {
            throw AmneziaWgPeerManagementException::removalFailed();
        }
    }

    public function runtimeStates(): array
    {
        try {
            $result = $this->privileged->executeWithResult(
                command: self::RUNTIME_COMMAND,
                timeout: SSHTimeout::QUICK,
            );
        } catch (Throwable) {
            throw AmneziaWgPeerManagementException::inspectionFailed();
        }

        if (! $result->successful()) {
            throw AmneziaWgPeerManagementException::inspectionFailed();
        }

        return $this->parseRuntimeOutput(
            $result->output,
        );
    }

    private function creationCommand(
        string $ipAddress,
        string $endpointHost,
    ): string {
        $endpointHost = trim($endpointHost);

        if ($endpointHost === '') {
            throw AmneziaWgPeerManagementException::creationFailed();
        }

        if (filter_var(
            $endpointHost,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6,
        ) !== false) {
            $endpointHost = '['.$endpointHost.']';
        }

        return strtr(
            self::CREATE_COMMAND,
            [
                '{{ peer_ip }}' => $this->shellArgument($ipAddress),
                '{{ endpoint_host }}' => $this->shellArgument($endpointHost),
            ],
        );
    }

    private function parseCreationOutput(
        string $output,
    ): AmneziaWgPeerProvisioningResult {
        $publicKey = null;
        $encodedConfig = null;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (str_starts_with($line, 'XDEPLOY_PUBLIC_KEY=')) {
                $publicKey = substr($line, strlen('XDEPLOY_PUBLIC_KEY='));
            }

            if (str_starts_with($line, 'XDEPLOY_CLIENT_CONFIG_B64=')) {
                $encodedConfig = substr(
                    $line,
                    strlen('XDEPLOY_CLIENT_CONFIG_B64='),
                );
            }
        }

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($encodedConfig)) {
            throw AmneziaWgPeerManagementException::creationFailed();
        }

        $clientConfig = base64_decode(
            $encodedConfig,
            true,
        );

        if (! is_string($clientConfig) || trim($clientConfig) === '') {
            throw AmneziaWgPeerManagementException::creationFailed();
        }

        return new AmneziaWgPeerProvisioningResult(
            publicKey: $publicKey,
            clientConfig: $clientConfig."\n",
        );
    }

    /**
     * @return list<AmneziaWgPeerRuntimeState>
     */
    private function parseRuntimeOutput(
        string $output,
    ): array {
        $output = str_replace("\r\n", "\n", $output);
        [$handshakeOutput, $transferOutput] = array_pad(
            explode("__XDEPLOY_TRANSFER__\n", $output, 2),
            2,
            '',
        );

        $states = [];

        foreach ($this->rows($handshakeOutput) as $row) {
            if (count($row) < 2) {
                continue;
            }

            $timestamp = (int) $row[1];
            $states[$row[0]] = [
                'latest_handshake_at' => $timestamp > 0 ? $timestamp : null,
                'received_bytes' => 0,
                'sent_bytes' => 0,
            ];
        }

        foreach ($this->rows($transferOutput) as $row) {
            if (count($row) < 3) {
                continue;
            }

            $states[$row[0]] ??= [
                'latest_handshake_at' => null,
                'received_bytes' => 0,
                'sent_bytes' => 0,
            ];

            $states[$row[0]]['received_bytes'] = max(0, (int) $row[1]);
            $states[$row[0]]['sent_bytes'] = max(0, (int) $row[2]);
        }

        $result = [];

        foreach ($states as $publicKey => $state) {
            $result[] = new AmneziaWgPeerRuntimeState(
                publicKey: $publicKey,
                latestHandshakeAt: $state['latest_handshake_at'],
                receivedBytes: $state['received_bytes'],
                sentBytes: $state['sent_bytes'],
            );
        }

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $output): array
    {
        $rows = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $columns = preg_split('/\s+/', trim($line));

            if (is_array($columns) && $columns !== []) {
                $rows[] = array_values($columns);
            }
        }

        return $rows;
    }

    private function shellArgument(
        string $value,
    ): string {
        return "'".str_replace(
            "'",
            "'\"'\"'",
            $value,
        )."'";
    }
}
