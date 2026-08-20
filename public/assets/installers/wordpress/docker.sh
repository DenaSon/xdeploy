#!/usr/bin/env bash

set -Eeuo pipefail

readonly APP_DIR="/opt/xdeploy/apps/wordpress"
readonly COMPOSE_FILE="$APP_DIR/docker-compose.yml"
readonly ENV_FILE="$APP_DIR/.env"
readonly MARKER_FILE="$APP_DIR/.xdeploy-install-complete"
readonly PROJECT_NAME="xdeploy-wordpress"
readonly WORDPRESS_IMAGE="wordpress:7.0.4-php8.3-apache@sha256:b427cec767f5de2aa649390cb8805aa1fe320e1e0d57fc1f467754edb6cc0a49"
readonly DATABASE_IMAGE="mariadb:11.4.12@sha256:4f1d8d202fcf7bcb3902f63af09f9c1a050c2922a89652f22abaec0d4f015e83"
readonly WORDPRESS_VOLUME="xdeploy-wordpress-data"
readonly DATABASE_VOLUME="xdeploy-wordpress-database"
readonly IMAGE_PULL_TIMEOUT_SECONDS=300
readonly IMAGE_PULL_KILL_AFTER_SECONDS=10
readonly RUNTIME_WAIT_ATTEMPTS=60
readonly RUNTIME_WAIT_DELAY_SECONDS=2

stage='initialization'

log() {
    printf '[xDeploy][WordPress] %s\n' "$1"
}

on_error() {
    local exit_code=$?

    printf '[xDeploy][WordPress][error] stage=%s exit_code=%s\n' \
        "$stage" \
        "$exit_code" >&2

    exit "$exit_code"
}

fail() {
    local message="$1"

    trap - ERR

    printf '[xDeploy][WordPress][error] stage=%s exit_code=1\n' \
        "$stage" >&2
    printf '[xDeploy][WordPress][error] %s\n' "$message" >&2

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

command -v openssl >/dev/null 2>&1 \
    || fail "OpenSSL is required to generate database credentials."

docker compose version >/dev/null 2>&1 \
    || fail "Docker Compose V2 is not available."

stage='application_directory'
install -d -m 0755 "$APP_DIR"

read_env_value() {
    local key="$1"

    awk -F= -v key="$key" '
        $1 == key {
            sub(/^[^=]*=/, "")
            gsub(/\r$/, "")
            print
            exit
        }
    ' "$ENV_FILE"
}

stage='environment_write'

if [[ -f "$ENV_FILE" ]]; then
    log "Validating the existing WordPress environment."

    for required_key in \
        WORDPRESS_DB_NAME \
        WORDPRESS_DB_USER \
        WORDPRESS_DB_PASSWORD \
        MARIADB_ROOT_PASSWORD; do
        key_count="$(
            awk -F= -v key="$required_key" '
                $1 == key {
                    count++
                }

                END {
                    print count + 0
                }
            ' "$ENV_FILE"
        )"

        [[ "$key_count" == "1" ]] \
            || fail "Existing WordPress environment must contain each required key exactly once."
    done

    wordpress_db_name="$(read_env_value WORDPRESS_DB_NAME)"
    wordpress_db_user="$(read_env_value WORDPRESS_DB_USER)"
    wordpress_db_password="$(read_env_value WORDPRESS_DB_PASSWORD)"
    mariadb_root_password="$(read_env_value MARIADB_ROOT_PASSWORD)"

    [[ "$wordpress_db_name" =~ ^[A-Za-z0-9_]+$ ]] \
        || fail "Existing WordPress database name is invalid."

    [[ "$wordpress_db_user" =~ ^[A-Za-z0-9_]+$ ]] \
        || fail "Existing WordPress database user is invalid."

    [[ "$wordpress_db_password" =~ ^[A-Fa-f0-9]{64}$ ]] \
        || fail "Existing WordPress database password is invalid."

    [[ "$mariadb_root_password" =~ ^[A-Fa-f0-9]{64}$ ]] \
        || fail "Existing MariaDB root password is invalid."
else
    if docker volume inspect "$DATABASE_VOLUME" >/dev/null 2>&1; then
        fail "Existing WordPress database storage cannot be reused without its xDeploy environment file."
    fi

    log "Generating the WordPress database environment."

    umask 077

    wordpress_db_password="$(openssl rand -hex 32)"
    mariadb_root_password="$(openssl rand -hex 32)"

    cat > "${ENV_FILE}.xdeploy-new" <<ENV
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=${wordpress_db_password}
MARIADB_ROOT_PASSWORD=${mariadb_root_password}
ENV

    mv -f "${ENV_FILE}.xdeploy-new" "$ENV_FILE"
fi

chmod 0600 "$ENV_FILE"

stage='compose_config_write'
log "Writing Docker Compose configuration."

cat > "${COMPOSE_FILE}.xdeploy-new" <<YAML
services:
  database:
    image: ${DATABASE_IMAGE}
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: \${WORDPRESS_DB_NAME}
      MARIADB_USER: \${WORDPRESS_DB_USER}
      MARIADB_PASSWORD: \${WORDPRESS_DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: \${MARIADB_ROOT_PASSWORD}
    volumes:
      - database_data:/var/lib/mysql
    healthcheck:
      test:
        - CMD
        - healthcheck.sh
        - --connect
        - --innodb_initialized
      interval: 5s
      timeout: 5s
      retries: 24
      start_period: 30s

  wordpress:
    image: ${WORDPRESS_IMAGE}
    restart: unless-stopped
    depends_on:
      database:
        condition: service_healthy
    environment:
      WORDPRESS_DB_HOST: database:3306
      WORDPRESS_DB_USER: \${WORDPRESS_DB_USER}
      WORDPRESS_DB_PASSWORD: \${WORDPRESS_DB_PASSWORD}
      WORDPRESS_DB_NAME: \${WORDPRESS_DB_NAME}
      XDEPLOY_WORDPRESS_PUBLIC_URL: \${XDEPLOY_WORDPRESS_PUBLIC_URL:-}
      WORDPRESS_CONFIG_EXTRA: >-
        if ((\$\$xdeployPublicUrl = getenv('XDEPLOY_WORDPRESS_PUBLIC_URL')) !== false && \$\$xdeployPublicUrl !== '') {
          define('WP_HOME', \$\$xdeployPublicUrl);
          define('WP_SITEURL', \$\$xdeployPublicUrl);
          define('FORCE_SSL_ADMIN', true);
        }
    ports:
      - "127.0.0.1:8080:80"
    volumes:
      - wordpress_data:/var/www/html
    healthcheck:
      test:
        - CMD-SHELL
        - >-
          php -r 'exit(@mysqli_connect("database", getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME")) !== false && @fsockopen("127.0.0.1", 80) !== false && is_file("/var/www/html/wp-includes/version.php") ? 0 : 1);'
      interval: 5s
      timeout: 5s
      retries: 24
      start_period: 20s

volumes:
  wordpress_data:
    name: ${WORDPRESS_VOLUME}
  database_data:
    name: ${DATABASE_VOLUME}
YAML

mv -f "${COMPOSE_FILE}.xdeploy-new" "$COMPOSE_FILE"
chmod 0644 "$COMPOSE_FILE"

stage='compose_validation'
log "Validating Docker Compose configuration."

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    config --quiet

stage='image_pull'
log "Pulling pinned WordPress and MariaDB images."

timeout \
    --signal=TERM \
    --kill-after="${IMAGE_PULL_KILL_AFTER_SECONDS}s" \
    "${IMAGE_PULL_TIMEOUT_SECONDS}s" \
    docker compose \
        --env-file "$ENV_FILE" \
        -f "$COMPOSE_FILE" \
        -p "$PROJECT_NAME" \
        pull

stage='compose_up'
log "Starting WordPress and MariaDB."

rm -f "$MARKER_FILE"

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    up -d --remove-orphans

service_health() {
    local service="$1"
    local container_id
    local health

    container_id="$(
        docker compose \
            --env-file "$ENV_FILE" \
            -f "$COMPOSE_FILE" \
            -p "$PROJECT_NAME" \
            ps -q "$service" 2>/dev/null || true
    )"

    if [[ -z "$container_id" ]]; then
        printf '%s\n' 'missing'

        return 0
    fi

    health="$(
        docker inspect \
            --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' \
            "$container_id" 2>/dev/null || true
    )"

    printf '%s\n' "${health:-unknown}"
}

stage='runtime_wait'
log "Waiting for WordPress runtime health."

for _attempt in $(seq 1 "$RUNTIME_WAIT_ATTEMPTS"); do
    database_health="$(service_health database)"
    wordpress_health="$(service_health wordpress)"

    if [[ "$database_health" == "healthy" ]] \
        && [[ "$wordpress_health" == "healthy" ]]; then
        stage='completion_marker'

        touch "$MARKER_FILE"
        chmod 0644 "$MARKER_FILE"

        trap - ERR

        log "WordPress installation completed successfully."
        exit 0
    fi

    sleep "$RUNTIME_WAIT_DELAY_SECONDS"
done

stage='runtime_verify'

docker compose \
    --env-file "$ENV_FILE" \
    -f "$COMPOSE_FILE" \
    -p "$PROJECT_NAME" \
    ps || true

fail "WordPress and MariaDB did not enter the healthy state."
