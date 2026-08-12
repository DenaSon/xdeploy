#!/usr/bin/env bash

set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

log() {
    printf '[xDeploy][caddy] %s\n' "$1"
}

fail() {
    printf '[xDeploy][caddy][error] %s\n' "$1" >&2
    exit 1
}

cleanup() {
    if [[ -n "${key_file:-}" && -f "$key_file" ]]; then
        rm -f "$key_file"
    fi

    if [[ -n "${repo_file:-}" && -f "$repo_file" ]]; then
        rm -f "$repo_file"
    fi
}

trap cleanup EXIT

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

log "Detected ${PRETTY_NAME:-${ID} ${VERSION_ID}}."

if command -v caddy >/dev/null 2>&1; then
    if ! systemctl cat caddy.service >/dev/null 2>&1; then
        fail 'Caddy binary exists but is not managed by the expected systemd service.'
    fi

    log 'Caddy is already installed.'

    systemctl enable --now caddy
    caddy version
    systemctl is-active --quiet caddy

    log 'Caddy is ready.'
    exit 0
fi

log 'Installing Caddy repository prerequisites.'
apt-get update
apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    curl \
    debian-archive-keyring \
    debian-keyring \
    gnupg

log 'Configuring the official Caddy APT repository.'
install -m 0755 -d /usr/share/keyrings
install -m 0755 -d /etc/apt/sources.list.d

key_file="$(mktemp)"
repo_file="$(mktemp)"

curl -1sLf \
    'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
    -o "$key_file"

gpg --batch --yes --dearmor \
    -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
    "$key_file"

curl -1sLf \
    'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
    -o "$repo_file"

install -m 0644 \
    "$repo_file" \
    /etc/apt/sources.list.d/caddy-stable.list

chmod o+r \
    /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
    /etc/apt/sources.list.d/caddy-stable.list

log 'Refreshing Caddy repository metadata.'
apt-get update

log 'Installing Caddy.'
apt-get install -y --no-install-recommends caddy

log 'Enabling Caddy service.'
systemctl enable --now caddy

log 'Verifying Caddy binary.'
caddy version

log 'Verifying Caddy service state.'
systemctl is-active --quiet caddy

log 'Caddy installation completed successfully.'
