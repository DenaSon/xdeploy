#!/usr/bin/env bash

set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

log() {
    printf '[xDeploy][docker] %s\n' "$1"
}

fail() {
    printf '[xDeploy][docker][error] %s\n' "$1" >&2
    exit 1
}

if [[ ! -r /etc/os-release ]]; then
    fail '/etc/os-release is not readable.'
fi

# shellcheck disable=SC1091
. /etc/os-release

if [[ "${ID:-}" != "ubuntu" ]]; then
    fail "Unsupported operating system: ${ID:-unknown}. This installer supports Ubuntu only."
fi

case "${VERSION_ID:-}" in
    "22.04"|"24.04")
        ;;
    *)
        fail "Unsupported Ubuntu version: ${VERSION_ID:-unknown}. Supported versions: 22.04, 24.04."
        ;;
esac

log "Detected Ubuntu ${VERSION_ID}."

log "Refreshing APT package metadata."
apt-get update

if ! apt-cache show docker-compose-v2 >/dev/null 2>&1; then
    log "Enabling Ubuntu Universe repository."

    apt-get install -y --no-install-recommends software-properties-common
    add-apt-repository -y universe
    apt-get update
fi

log "Installing Docker Engine and Docker Compose V2 from Ubuntu repositories."

apt-get install -y --no-install-recommends     ca-certificates     curl     docker.io     docker-compose-v2

log "Enabling Docker service."
systemctl enable --now docker

log "Verifying Docker CLI."
docker --version

log "Verifying Docker Compose V2."
docker compose version

log "Verifying Docker service state."
systemctl is-active --quiet docker

log "Docker installation completed successfully."
