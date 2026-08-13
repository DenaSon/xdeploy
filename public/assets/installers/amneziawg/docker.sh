#!/usr/bin/env bash

set -Eeuo pipefail

readonly APP_DIR="/opt/xdeploy/apps/amneziawg"
readonly DATA_DIR="$APP_DIR/data"
readonly COMPOSE_FILE="$APP_DIR/docker-compose.yml"
readonly ENV_FILE="$APP_DIR/.env"
readonly START_SCRIPT="$APP_DIR/start.sh"
readonly MARKER_FILE="$APP_DIR/.xdeploy-install-complete"
readonly IMAGE="amneziavpn/amneziawg-go:0.2.19@sha256:acef5ae84808a9568448e9d8c7a96f640a5ccc590b0f8dfbc2df9f9dc0e848c9"
readonly CONTAINER_NAME="amnezia-awg2"
readonly PROJECT_NAME="amneziawg"
readonly SERVICE_NAME="amneziawg"
readonly IMAGE_PULL_TIMEOUT_SECONDS=300
readonly IMAGE_PULL_KILL_AFTER_SECONDS=10

stage='initialization'

log() {
    printf '[xDeploy][AmneziaWG] %s\n' "$1"
}

on_error() {
    local exit_code=$?

    printf '[xDeploy][AmneziaWG][error] stage=%s exit_code=%s\n' \
        "$stage" \
        "$exit_code" >&2

    exit "$exit_code"
}

fail() {
    local message="$1"

    trap - ERR

    printf '[xDeploy][AmneziaWG][error] stage=%s exit_code=1\n' \
        "$stage" >&2
    printf '[xDeploy][AmneziaWG][error] %s\n' "$message" >&2

    exit 1
}

trap on_error ERR

stage='prerequisites'

if [[ "$(id -u)" != "0" ]]; then
    fail "Installer must run as root."
fi

command -v docker >/dev/null 2>&1 \
    || fail "Docker is not installed."

command -v timeout >/dev/null 2>&1 \
    || fail "GNU timeout is required to bound image pulls."

command -v ss >/dev/null 2>&1 \
    || fail "The ss command is required to select a free UDP port."

command -v od >/dev/null 2>&1 \
    || fail "The od command is required to generate protocol parameters."

command -v sort >/dev/null 2>&1 \
    || fail "The sort command is required to generate protocol parameters."

[[ -c /dev/net/tun ]] \
    || fail "/dev/net/tun is not available on this server."

docker compose version >/dev/null 2>&1 \
    || fail "Docker Compose V2 is not available."

case "$(uname -m)" in
    x86_64|amd64) ;;
    *)
        fail "AmneziaWG AWG 2.0 runtime is currently supported only on amd64 servers."
        ;;
esac

stage='existing_runtime_check'

existing_container_id="$(
    docker ps -aq \
        --filter "name=^/${CONTAINER_NAME}$" \
        | head -n 1
)"

if [[ -n "$existing_container_id" ]]; then
    existing_project="$(
        docker inspect \
            --format '{{ index .Config.Labels "com.docker.compose.project" }}' \
            "$existing_container_id" 2>/dev/null || true
    )"

    if [[ "$existing_project" != "$PROJECT_NAME" ]]; then
        fail "An unmanaged amnezia-awg2 container already exists on this server."
    fi
fi

stage='application_directory'
install -d -m 0755 "$APP_DIR"
install -d -m 0700 "$DATA_DIR"

readonly SERVER_CONFIG_FILE="$DATA_DIR/awg0.conf"

random_u31() {
    local value

    value="$(
        od -An -N4 -tu4 /dev/urandom \
            | tr -d '[:space:]'
    )"

    printf '%u\n' "$((value % 2147483643 + 5))"
}

select_udp_port() {
    local candidate

    for _attempt in $(seq 1 128); do
        candidate="$((1024 + (RANDOM * 2 + RANDOM) % 64512))"

        if ! ss -H -lun \
            | awk '{print $5}' \
            | grep -Eq "(^|:)$candidate$"; then
            printf '%s\n' "$candidate"

            return 0
        fi
    done

    return 1
}

read_existing_port() {
    awk -F= '
        $1 == "AWG_PORT" {
            gsub(/[[:space:]]/, "", $2)
            print $2
        }
    ' "$ENV_FILE" 2>/dev/null \
        | tail -n 1
}

stage='environment_write'

awg_port=''

if [[ -f "$ENV_FILE" ]]; then
    awg_port="$(read_existing_port)"
fi

if [[ ! "$awg_port" =~ ^[0-9]+$ ]] \
    || ((awg_port < 1024 || awg_port > 65535)); then
    if [[ -s "$SERVER_CONFIG_FILE" ]]; then
        awg_port="$(
            awk -F= '
                $1 ~ /^[[:space:]]*ListenPort[[:space:]]*$/ {
                    gsub(/[[:space:]]/, "", $2)
                    print $2
                }
            ' "$SERVER_CONFIG_FILE" \
                | tail -n 1
        )"

        [[ "$awg_port" =~ ^[0-9]+$ ]] \
            && ((awg_port >= 1024 && awg_port <= 65535)) \
            || fail "Existing AmneziaWG configuration has an invalid ListenPort."
    else
        awg_port="$(select_udp_port)" \
            || fail "Unable to allocate a free UDP port for AmneziaWG."
    fi

    cat > "${ENV_FILE}.xdeploy-new" <<ENV
AWG_PORT=${awg_port}
ENV

    mv -f "${ENV_FILE}.xdeploy-new" "$ENV_FILE"
    chmod 0600 "$ENV_FILE"
fi

stage='compose_config_write'
log "Writing Docker Compose configuration."

cat > "${COMPOSE_FILE}.xdeploy-new" <<YAML
services:
  ${SERVICE_NAME}:
    image: ${IMAGE}
    container_name: ${CONTAINER_NAME}
    restart: unless-stopped
    cap_drop:
      - ALL
    cap_add:
      - NET_ADMIN
    devices:
      - /dev/net/tun:/dev/net/tun
    sysctls:
      net.ipv4.conf.all.src_valid_mark: "1"
      net.ipv4.ip_forward: "1"
    ports:
      - "\${AWG_PORT}:\${AWG_PORT}/udp"
    volumes:
      - ./data:/opt/amnezia/awg
      - ./start.sh:/opt/amnezia/start.sh:ro
    command:
      - /bin/bash
      - /opt/amnezia/start.sh
YAML

mv -f "${COMPOSE_FILE}.xdeploy-new" "$COMPOSE_FILE"
chmod 0644 "$COMPOSE_FILE"

stage='startup_script_write'
log "Writing AmneziaWG startup script."

cat > "${START_SCRIPT}.xdeploy-new" <<'BASH'
#!/usr/bin/env bash

set -Eeuo pipefail

readonly CONFIG='/opt/amnezia/awg/awg0.conf'
readonly SUBNET='10.8.1.0/24'

stop_awg() {
    awg-quick down "$CONFIG" >/dev/null 2>&1 || true
}

on_signal() {
    stop_awg
    exit 0
}

trap on_signal TERM INT

stop_awg
awg-quick up "$CONFIG"

iptables -C INPUT -i awg0 -j ACCEPT >/dev/null 2>&1 \
    || iptables -A INPUT -i awg0 -j ACCEPT

iptables -C FORWARD -i awg0 -j ACCEPT >/dev/null 2>&1 \
    || iptables -A FORWARD -i awg0 -j ACCEPT

iptables -C OUTPUT -o awg0 -j ACCEPT >/dev/null 2>&1 \
    || iptables -A OUTPUT -o awg0 -j ACCEPT

iptables -C FORWARD -i awg0 -o eth0 -s "$SUBNET" -j ACCEPT >/dev/null 2>&1 \
    || iptables -A FORWARD -i awg0 -o eth0 -s "$SUBNET" -j ACCEPT

iptables -C FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT >/dev/null 2>&1 \
    || iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

iptables -t nat -C POSTROUTING -s "$SUBNET" -o eth0 -j MASQUERADE >/dev/null 2>&1 \
    || iptables -t nat -A POSTROUTING -s "$SUBNET" -o eth0 -j MASQUERADE

while true; do
    sleep 3600 &
    wait $!
done
BASH

mv -f "${START_SCRIPT}.xdeploy-new" "$START_SCRIPT"
chmod 0755 "$START_SCRIPT"

stage='compose_validation'
log "Validating Docker Compose configuration."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    config --quiet

stage='image_pull'
log "Pulling pinned AmneziaWG image."

timeout \
    --signal=TERM \
    --kill-after="${IMAGE_PULL_KILL_AFTER_SECONDS}s" \
    "${IMAGE_PULL_TIMEOUT_SECONDS}s" \
    docker compose \
        --env-file "$ENV_FILE" \
        -f "$COMPOSE_FILE" \
        -p "$PROJECT_NAME" \
        pull

stage='runtime_state'

private_key_file="$DATA_DIR/wireguard_server_private_key.key"
public_key_file="$DATA_DIR/wireguard_server_public_key.key"
psk_file="$DATA_DIR/wireguard_psk.key"
config_file="$SERVER_CONFIG_FILE"
clients_table_file="$DATA_DIR/clientsTable"

if [[ -s "$config_file" ]]; then
    configured_port="$(
        awk -F= '
            $1 ~ /^[[:space:]]*ListenPort[[:space:]]*$/ {
                gsub(/[[:space:]]/, "", $2)
                print $2
            }
        ' "$config_file" \
            | tail -n 1
    )"

    [[ "$configured_port" == "$awg_port" ]] \
        || fail "Persisted AmneziaWG ListenPort does not match the application environment."
fi

if [[ -s "$config_file" ]] && {
    [[ ! -s "$private_key_file" ]] \
        || [[ ! -s "$public_key_file" ]] \
        || [[ ! -s "$psk_file" ]]
}; then
    fail "Existing AmneziaWG configuration has incomplete server key state."
fi

if [[ ! -s "$private_key_file" ]] \
    || [[ ! -s "$public_key_file" ]] \
    || [[ ! -s "$psk_file" ]]; then
    log "Generating AmneziaWG server keys."

    private_key="$(
        docker run --rm \
            --entrypoint awg \
            "$IMAGE" \
            genkey
    )"

    [[ -n "$private_key" ]] \
        || fail "Unable to generate the AmneziaWG server private key."

    public_key="$(
        printf '%s\n' "$private_key" \
            | docker run --rm -i \
                --entrypoint awg \
                "$IMAGE" \
                pubkey
    )"

    psk="$(
        docker run --rm \
            --entrypoint awg \
            "$IMAGE" \
            genpsk
    )"

    [[ -n "$public_key" && -n "$psk" ]] \
        || fail "Unable to generate AmneziaWG server keys."

    printf '%s\n' "$private_key" \
        > "${private_key_file}.xdeploy-new"
    printf '%s\n' "$public_key" \
        > "${public_key_file}.xdeploy-new"
    printf '%s\n' "$psk" \
        > "${psk_file}.xdeploy-new"

    mv -f "${private_key_file}.xdeploy-new" "$private_key_file"
    mv -f "${public_key_file}.xdeploy-new" "$public_key_file"
    mv -f "${psk_file}.xdeploy-new" "$psk_file"

    chmod 0600 "$private_key_file" "$public_key_file" "$psk_file"
fi

if [[ ! -s "$config_file" ]]; then
    log "Generating AWG 2.0 server configuration."

    private_key="$(tr -d '\r\n' < "$private_key_file")"

    jc="$((4 + RANDOM % 3))"
    jmin=10
    jmax=50
    s1="$((15 + RANDOM % 135))"
    s2="$((15 + RANDOM % 135))"
    s3="$((RANDOM % 64))"
    s4="$((RANDOM % 20))"

    mapfile -t headers < <(
        for _index in $(seq 1 8); do
            random_u31
        done \
            | sort -n -u
    )

    while ((${#headers[@]} < 8)); do
        headers+=("$(random_u31)")
        mapfile -t headers < <(
            printf '%s\n' "${headers[@]}" \
                | sort -n -u
        )
    done

    h1="${headers[0]}-${headers[1]}"
    h2="${headers[2]}-${headers[3]}"
    h3="${headers[4]}-${headers[5]}"
    h4="${headers[6]}-${headers[7]}"

    cat > "${config_file}.xdeploy-new" <<CONF
[Interface]
PrivateKey = ${private_key}
Address = 10.8.1.0/24
ListenPort = ${awg_port}
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
CONF

    mv -f "${config_file}.xdeploy-new" "$config_file"
    chmod 0600 "$config_file"
fi

if [[ ! -f "$clients_table_file" ]]; then
    printf '[]\n' > "${clients_table_file}.xdeploy-new"
    mv -f "${clients_table_file}.xdeploy-new" "$clients_table_file"
    chmod 0600 "$clients_table_file"
fi

stage='compose_up'
log "Starting AmneziaWG."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    up -d --remove-orphans

stage='runtime_verify'
log "Waiting for AmneziaWG runtime."

for attempt in $(seq 1 30); do
    container_id="$(
        docker compose \
            --env-file "$ENV_FILE" \
            -f "$COMPOSE_FILE" \
            -p "$PROJECT_NAME" \
            ps -q "$SERVICE_NAME" 2>/dev/null || true
    )"

    if [[ -n "$container_id" ]] \
        && [[ "$(
            docker inspect \
                --format '{{.State.Running}}' \
                "$container_id" 2>/dev/null || true
        )" == 'true' ]] \
        && docker exec "$CONTAINER_NAME" \
            awg show awg0 >/dev/null 2>&1; then
        stage='completion_marker'

        touch "$MARKER_FILE"
        chmod 0644 "$MARKER_FILE"

        trap - ERR

        log "AmneziaWG installation completed successfully."
        exit 0
    fi

    sleep 2
done

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    ps || true

docker logs \
    --tail 100 \
    "$CONTAINER_NAME" 2>/dev/null || true

fail "AmneziaWG did not enter a healthy running state."
