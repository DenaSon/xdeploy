#!/usr/bin/env bash

set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

readonly INSTALLER_TIMEOUT_SECONDS=300
readonly INSTALLER_KILL_AFTER_SECONDS=10
readonly APT_LOCK_TIMEOUT_SECONDS=120
readonly APT_ACQUIRE_TIMEOUT_SECONDS=45
readonly APT_RETRIES=3

stage='initialization'

log() {
    printf '[xDeploy][docker] %s\n' "$1"
}

fail() {
    printf '[xDeploy][docker][error] %s\n' "$1" >&2
    exit 1
}

on_error() {
    local exit_code=$?

    printf '[xDeploy][docker][error] stage=%s exit_code=%s\n' \
        "$stage" \
        "$exit_code" >&2

    exit "$exit_code"
}

apt_get() {
    apt-get \
        -o "DPkg::Lock::Timeout=${APT_LOCK_TIMEOUT_SECONDS}" \
        -o "Acquire::Retries=${APT_RETRIES}" \
        -o "Acquire::http::Timeout=${APT_ACQUIRE_TIMEOUT_SECONDS}" \
        -o "Acquire::https::Timeout=${APT_ACQUIRE_TIMEOUT_SECONDS}" \
        "$@"
}

trap on_error ERR

if [[ "$(id -u)" != "0" ]]; then
    fail 'Installer must run as root.'
fi

if ! command -v timeout >/dev/null 2>&1; then
    fail 'GNU timeout is required to enforce the installer lifecycle.'
fi

if [[ "${XDEPLOY_DOCKER_INSTALLER_TIMED:-0}" != "1" ]]; then
    export XDEPLOY_DOCKER_INSTALLER_TIMED=1

    exec timeout \
        --signal=TERM \
        --kill-after="${INSTALLER_KILL_AFTER_SECONDS}s" \
        "${INSTALLER_TIMEOUT_SECONDS}s" \
        bash "$0"
fi

if [[ ! -r /etc/os-release ]]; then
    fail '/etc/os-release is not readable.'
fi

# shellcheck disable=SC1091
. /etc/os-release

case "${ID:-}:${VERSION_ID:-}" in
    "debian:12"|"ubuntu:22.04"|"ubuntu:24.04"|"ubuntu:26.04")
        ;;
    *)
        fail "Unsupported operating system: ${ID:-unknown} ${VERSION_ID:-unknown}. Supported systems: Debian 12, Ubuntu 22.04, Ubuntu 24.04, Ubuntu 26.04."
        ;;
esac

if [[ -z "${VERSION_CODENAME:-}" ]]; then
    fail 'VERSION_CODENAME is missing from /etc/os-release.'
fi

log "Detected ${PRETTY_NAME:-${ID} ${VERSION_ID}}."

if command -v docker >/dev/null 2>&1 \
    && docker compose version >/dev/null 2>&1; then
    log 'Docker Engine and Docker Compose V2 are already available.'

    stage='enable_existing_docker'
    systemctl enable --now docker

    stage='verify_existing_docker'
    docker --version
    docker compose version
    docker buildx version
    systemctl is-active --quiet docker

    log 'Docker stack is ready.'
    exit 0
fi

stage='apt_prerequisites_update'
log 'Refreshing operating-system package metadata.'
apt_get update

stage='apt_prerequisites_install'
log 'Installing Docker repository prerequisites.'
apt_get install -y --no-install-recommends \
    ca-certificates \
    curl

stage='docker_repository_key'
log 'Configuring the official Docker APT repository.'
install -m 0755 -d /etc/apt/keyrings
curl \
    --proto '=https' \
    --tlsv1.2 \
    --fail \
    --silent \
    --show-error \
    --location \
    --retry 3 \
    --retry-all-errors \
    --connect-timeout 10 \
    --max-time 60 \
    "https://download.docker.com/linux/${ID}/gpg" \
    -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

architecture="$(dpkg --print-architecture)"

cat > /etc/apt/sources.list.d/docker.list <<EOF_REPOSITORY
deb [arch=${architecture} signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${ID} ${VERSION_CODENAME} stable
EOF_REPOSITORY

stage='docker_repository_update'
log 'Refreshing Docker repository metadata.'
apt_get update

conflicting_packages=(
    docker.io
    docker-doc
    docker-compose
    docker-compose-v2
    podman-docker
    containerd
    runc
)

installed_conflicts=()

for package in "${conflicting_packages[@]}"; do
    if dpkg-query -W -f='${Status}' -- "$package" 2>/dev/null \
        | grep -q '^install ok installed$'; then
        installed_conflicts+=("$package")
    fi
done

if (( ${#installed_conflicts[@]} > 0 )); then
    stage='remove_conflicting_packages'
    log 'Removing packages that conflict with Docker CE.'
    apt_get remove -y "${installed_conflicts[@]}"
fi

stage='install_docker_ce'
log 'Installing Docker Engine, Buildx, and Docker Compose V2.'
apt_get install -y --no-install-recommends \
    docker-ce \
    docker-ce-cli \
    containerd.io \
    docker-buildx-plugin \
    docker-compose-plugin

stage='enable_docker_service'
log 'Enabling Docker service.'
systemctl enable --now docker

stage='verify_docker_cli'
log 'Verifying Docker CLI.'
docker --version

stage='verify_docker_compose'
log 'Verifying Docker Compose V2.'
docker compose version

stage='verify_docker_buildx'
log 'Verifying Docker Buildx.'
docker buildx version

stage='verify_docker_service'
log 'Verifying Docker service state.'
systemctl is-active --quiet docker

trap - ERR

log 'Docker installation completed successfully.'
