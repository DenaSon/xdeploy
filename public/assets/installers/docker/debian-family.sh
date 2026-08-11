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

if [[ "$(id -u)" != "0" ]]; then
    fail 'Installer must run as root.'
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

    systemctl enable --now docker
    docker --version
    docker compose version
    systemctl is-active --quiet docker

    log 'Docker stack is ready.'
    exit 0
fi

log 'Installing Docker repository prerequisites.'
apt-get update
apt-get install -y --no-install-recommends \
    ca-certificates \
    curl

log 'Configuring the official Docker APT repository.'
install -m 0755 -d /etc/apt/keyrings
curl -fsSL "https://download.docker.com/linux/${ID}/gpg" \
    -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

architecture="$(dpkg --print-architecture)"

cat > /etc/apt/sources.list.d/docker.list <<EOF
deb [arch=${architecture} signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${ID} ${VERSION_CODENAME} stable
EOF

log 'Refreshing Docker repository metadata.'
apt-get update

log 'Removing packages that conflict with Docker CE, when present.'
for package in \
    docker.io \
    docker-doc \
    docker-compose \
    docker-compose-v2 \
    podman-docker \
    containerd \
    runc
do
    apt-get remove -y "$package" >/dev/null 2>&1 || true
done

log 'Installing Docker Engine and Docker Compose V2.'
apt-get install -y --no-install-recommends \
    docker-ce \
    docker-ce-cli \
    containerd.io \
    docker-buildx-plugin \
    docker-compose-plugin

log 'Enabling Docker service.'
systemctl enable --now docker

log 'Verifying Docker CLI.'
docker --version

log 'Verifying Docker Compose V2.'
docker compose version

log 'Verifying Docker service state.'
systemctl is-active --quiet docker

log 'Docker installation completed successfully.'
