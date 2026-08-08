#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/opt/marzban"
DATA_DIR="/var/lib/marzban"
COMPOSE_FILE="$APP_DIR/docker-compose.yml"
ENV_FILE="$APP_DIR/.env"
XRAY_CONFIG="$DATA_DIR/xray_config.json"
MARKER_FILE="$APP_DIR/.xdeploy-install-complete"

log() {
    printf '[xDeploy][marzban] %s\n' "$1"
}

fail() {
    printf '[xDeploy][marzban][error] %s\n' "$1" >&2
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
install -d -m 0755 "$DATA_DIR"

log "Writing docker-compose.yml."

cat > "${COMPOSE_FILE}.xdeploy-new" <<'YAML'
services:
  marzban:
    image: ghcr.io/gozargah/marzban:latest
    restart: always
    env_file: .env
    network_mode: host
    volumes:
      - /var/lib/marzban:/var/lib/marzban
YAML

mv -f "${COMPOSE_FILE}.xdeploy-new" "$COMPOSE_FILE"
chmod 0644 "$COMPOSE_FILE"

log "Writing Marzban environment."

cat > "${ENV_FILE}.xdeploy-new" <<'ENV'
UVICORN_HOST = "0.0.0.0"
UVICORN_PORT = 8000

XRAY_JSON = "/var/lib/marzban/xray_config.json"
SQLALCHEMY_DATABASE_URL = "sqlite:////var/lib/marzban/db.sqlite3"
ENV

mv -f "${ENV_FILE}.xdeploy-new" "$ENV_FILE"
chmod 0600 "$ENV_FILE"

log "Writing Xray configuration."

cat > "${XRAY_CONFIG}.xdeploy-new" <<'JSON'
{
  "log": {
    "loglevel": "warning"
  },
  "routing": {
    "rules": [
      {
        "ip": [
          "geoip:private"
        ],
        "outboundTag": "BLOCK",
        "type": "field"
      }
    ]
  },
  "inbounds": [
    {
      "tag": "Shadowsocks TCP",
      "listen": "0.0.0.0",
      "port": 1080,
      "protocol": "shadowsocks",
      "settings": {
        "clients": [],
        "network": "tcp,udp"
      }
    }
  ],
  "outbounds": [
    {
      "protocol": "freedom",
      "tag": "DIRECT"
    },
    {
      "protocol": "blackhole",
      "tag": "BLOCK"
    }
  ]
}
JSON

mv -f "${XRAY_CONFIG}.xdeploy-new" "$XRAY_CONFIG"
chmod 0644 "$XRAY_CONFIG"

log "Validating Docker Compose configuration."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p marzban \
    config --quiet

log "Pulling Marzban image from GHCR."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p marzban \
    pull

log "Starting Marzban."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p marzban \
    up -d --remove-orphans

log "Waiting for Marzban container."

for attempt in $(seq 1 30); do
    container_id="$(
        docker compose \
            --env-file "$ENV_FILE" \
            -f "$COMPOSE_FILE" \
            -p marzban \
            ps -q marzban 2>/dev/null || true
    )"

    if [[ -n "$container_id" ]] && \
       [[ "$(
            docker inspect \
                --format '{{.State.Running}}' \
                "$container_id" 2>/dev/null || true
       )" == "true" ]]; then
        touch "$MARKER_FILE"
        chmod 0644 "$MARKER_FILE"

        log "Marzban installation completed successfully."
        exit 0
    fi

    sleep 2
done

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p marzban \
    ps || true

fail "Marzban container did not enter the running state."
