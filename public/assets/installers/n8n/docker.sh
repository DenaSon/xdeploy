#!/usr/bin/env bash

set -Eeuo pipefail

readonly APP_DIR="/opt/n8n"
readonly COMPOSE_FILE="$APP_DIR/docker-compose.yml"
readonly ENV_FILE="$APP_DIR/.env"
readonly MARKER_FILE="$APP_DIR/.xdeploy-install-complete"
readonly IMAGE="docker.n8n.io/n8nio/n8n:2.32.6"
readonly DATA_VOLUME="xdeploy-n8n-data"
readonly IMAGE_PULL_TIMEOUT_SECONDS=300
readonly IMAGE_PULL_KILL_AFTER_SECONDS=10

stage='initialization'

log() {
    printf '[xDeploy][n8n] %s\n' "$1"
}

on_error() {
    local exit_code=$?

    printf '[xDeploy][n8n][error] stage=%s exit_code=%s\n' \
        "$stage" \
        "$exit_code" >&2

    exit "$exit_code"
}

fail() {
    local message="$1"

    trap - ERR

    printf '[xDeploy][n8n][error] stage=%s exit_code=1\n' \
        "$stage" >&2
    printf '[xDeploy][n8n][error] %s\n' "$message" >&2

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

docker compose version >/dev/null 2>&1 \
    || fail "Docker Compose V2 is not available."

stage='application_directory'
install -d -m 0755 "$APP_DIR"

stage='compose_config_write'
log "Writing docker-compose.yml."

cat > "${COMPOSE_FILE}.xdeploy-new" <<YAML
services:
  n8n:
    image: ${IMAGE}
    restart: unless-stopped
    env_file:
      - .env
    ports:
      - "127.0.0.1:5678:5678"
    volumes:
      - n8n_data:/home/node/.n8n

volumes:
  n8n_data:
    name: ${DATA_VOLUME}
YAML

mv -f "${COMPOSE_FILE}.xdeploy-new" "$COMPOSE_FILE"
chmod 0644 "$COMPOSE_FILE"

stage='environment_write'
log "Writing n8n environment."

cat > "${ENV_FILE}.xdeploy-new" <<'ENV'
GENERIC_TIMEZONE=UTC
TZ=UTC
N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=true
ENV

mv -f "${ENV_FILE}.xdeploy-new" "$ENV_FILE"
chmod 0600 "$ENV_FILE"

stage='compose_validation'
log "Validating Docker Compose configuration."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    config --quiet

stage='image_pull'
log "Pulling n8n image."

timeout \
    --signal=TERM \
    --kill-after="${IMAGE_PULL_KILL_AFTER_SECONDS}s" \
    "${IMAGE_PULL_TIMEOUT_SECONDS}s" \
    docker compose \
        --env-file "$ENV_FILE" \
        -f "$COMPOSE_FILE" \
        -p n8n \
        pull

stage='compose_up'
log "Starting n8n."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    up -d --remove-orphans

stage='container_wait'
log "Waiting for n8n container."

for attempt in $(seq 1 30); do
    container_id="$(
        docker compose \
            --env-file "$ENV_FILE" \
            -f "$COMPOSE_FILE" \
            -p n8n \
            ps -q n8n 2>/dev/null || true
    )"

    if [[ -n "$container_id" ]] && \
       [[ "$(
            docker inspect \
                --format '{{.State.Running}}' \
                "$container_id" 2>/dev/null || true
       )" == "true" ]]; then
        stage='completion_marker'

        touch "$MARKER_FILE"
        chmod 0644 "$MARKER_FILE"

        trap - ERR

        log "n8n installation completed successfully."
        exit 0
    fi

    sleep 2
done

stage='container_verify'

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    ps || true

fail "n8n container did not enter the running state."
