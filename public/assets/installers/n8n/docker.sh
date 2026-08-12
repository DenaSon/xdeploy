#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/opt/n8n"
COMPOSE_FILE="$APP_DIR/docker-compose.yml"
ENV_FILE="$APP_DIR/.env"
MARKER_FILE="$APP_DIR/.xdeploy-install-complete"
IMAGE="docker.n8n.io/n8nio/n8n:2.32.6"
DATA_VOLUME="xdeploy-n8n-data"

log() {
    printf '[xDeploy][n8n] %s\n' "$1"
}

fail() {
    printf '[xDeploy][n8n][error] %s\n' "$1" >&2
    exit 1
}

if [[ "$(id -u)" != "0" ]]; then
    fail "Installer must run as root."
fi

command -v docker >/dev/null 2>&1 \
    || fail "Docker is not installed."

docker compose version >/dev/null 2>&1 \
    || fail "Docker Compose V2 is not available."

install -d -m 0755 "$APP_DIR"

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

log "Writing n8n environment."

cat > "${ENV_FILE}.xdeploy-new" <<'ENV'
GENERIC_TIMEZONE=UTC
TZ=UTC
N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=true
ENV

mv -f "${ENV_FILE}.xdeploy-new" "$ENV_FILE"
chmod 0600 "$ENV_FILE"

log "Validating Docker Compose configuration."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    config --quiet

log "Pulling n8n image."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    pull

log "Starting n8n."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    up -d --remove-orphans

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
        touch "$MARKER_FILE"
        chmod 0644 "$MARKER_FILE"

        log "n8n installation completed successfully."
        exit 0
    fi

    sleep 2
done

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p n8n \
    ps || true

fail "n8n container did not enter the running state."
