#!/usr/bin/env bash

set -Eeuo pipefail

export DEBIAN_FRONTEND=noninteractive

readonly INSTALLER_TIMEOUT_SECONDS=1800
readonly INSTALLER_KILL_AFTER_SECONDS=10
readonly APT_LOCK_TIMEOUT_SECONDS=120
readonly APT_ACQUIRE_TIMEOUT_SECONDS=45
readonly APT_RETRIES=3
readonly XDEPLOY_DOCKER_KEYRING='/etc/apt/keyrings/xdeploy-docker.asc'
readonly XDEPLOY_DOCKER_SOURCE='/etc/apt/sources.list.d/xdeploy-docker.list'

stage='initialization'

official_docker_packages=(
    docker-ce
    docker-ce-cli
    containerd.io
    docker-buildx-plugin
    docker-compose-plugin
)

ubuntu_docker_packages=(
    docker.io
    docker-compose-v2
    docker-buildx
)

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
        -o Acquire::ForceIPv4=true \
        -o "Acquire::Retries=${APT_RETRIES}" \
        -o "Acquire::http::Timeout=${APT_ACQUIRE_TIMEOUT_SECONDS}" \
        -o "Acquire::https::Timeout=${APT_ACQUIRE_TIMEOUT_SECONDS}" \
        "$@"
}

package_is_installed() {
    dpkg-query -W -f='${Status}' -- "$1" 2>/dev/null \
        | grep -q '^install ok installed$'
}

package_has_candidate() {
    local candidate

    candidate="$(
        apt-cache policy "$1" \
            | awk '/Candidate:/ { print $2; exit }'
    )"

    [[ -n "$candidate" && "$candidate" != '(none)' ]]
}

all_packages_have_candidates() {
    local package

    for package in "$@"; do
        if ! package_has_candidate "$package"; then
            return 1
        fi
    done

    return 0
}

remove_xdeploy_official_repository() {
    rm -f \
        "$XDEPLOY_DOCKER_SOURCE" \
        "$XDEPLOY_DOCKER_KEYRING"
}

verify_docker_stack() {
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
}

install_ubuntu_repository_fallback() {
    local reason="$1"
    local package

    log "Official Docker repository is unavailable during [${reason}]. Falling back to Ubuntu packages."

    remove_xdeploy_official_repository

    stage='ubuntu_fallback_repository_update'
    apt_get update

    stage='ubuntu_fallback_package_preflight'
    if ! all_packages_have_candidates "${ubuntu_docker_packages[@]}"; then
        fail 'Ubuntu Docker fallback packages are not available for this release.'
    fi

    if ! apt_get install --dry-run --no-install-recommends \
        "${ubuntu_docker_packages[@]}" >/dev/null; then
        fail 'The Ubuntu Docker fallback package transaction cannot be resolved safely.'
    fi

    for package in "${official_docker_packages[@]}"; do
        if package_is_installed "$package"; then
            fail 'Refusing Ubuntu Docker fallback because Docker CE packages are already installed.'
        fi
    done

    stage='ubuntu_fallback_package_download'
    log 'Downloading Docker Engine, Buildx, and Docker Compose V2 from Ubuntu repositories.'
    apt_get install -y --download-only --no-install-recommends \
        "${ubuntu_docker_packages[@]}"

    stage='ubuntu_fallback_package_install'
    log 'Installing the downloaded Ubuntu Docker package set.'
    apt_get install -y --no-download --no-install-recommends \
        "${ubuntu_docker_packages[@]}"

    verify_docker_stack

    trap - ERR

    log 'Docker installation completed successfully using Ubuntu repository fallback.'
    exit 0
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
    && docker compose version >/dev/null 2>&1 \
    && docker buildx version >/dev/null 2>&1; then
    log 'Docker Engine, Docker Compose V2, and Docker Buildx are already available.'

    verify_docker_stack

    trap - ERR

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

key_candidate="$(mktemp)"
trap 'rm -f "$key_candidate"' EXIT

if ! curl \
    --ipv4 \
    --http1.1 \
    --proto '=https' \
    --tlsv1.2 \
    --fail \
    --silent \
    --show-error \
    --location \
    --retry 2 \
    --retry-delay 2 \
    --retry-all-errors \
    --connect-timeout 10 \
    --max-time 30 \
    "https://download.docker.com/linux/${ID}/gpg" \
    -o "$key_candidate"; then
    if [[ "$ID" == 'ubuntu' ]]; then
        install_ubuntu_repository_fallback "$stage"
    fi

    fail 'Unable to download the official Docker repository signing key.'
fi

install -m 0644 \
    "$key_candidate" \
    "$XDEPLOY_DOCKER_KEYRING"
rm -f "$key_candidate"
trap - EXIT

architecture="$(dpkg --print-architecture)"

cat > "$XDEPLOY_DOCKER_SOURCE" <<EOF_REPOSITORY
deb [arch=${architecture} signed-by=${XDEPLOY_DOCKER_KEYRING}] https://download.docker.com/linux/${ID} ${VERSION_CODENAME} stable
EOF_REPOSITORY

stage='docker_repository_update'
log 'Refreshing Docker repository metadata.'

if ! apt_get update; then
    if [[ "$ID" == 'ubuntu' ]]; then
        install_ubuntu_repository_fallback "$stage"
    fi

    fail 'Unable to refresh the official Docker repository metadata.'
fi

stage='docker_ce_package_preflight'

if ! all_packages_have_candidates "${official_docker_packages[@]}"; then
    if [[ "$ID" == 'ubuntu' ]]; then
        install_ubuntu_repository_fallback "$stage"
    fi

    fail 'The complete Docker CE package set is not available from the official repository.'
fi

if ! apt_get install --dry-run --no-install-recommends \
    "${official_docker_packages[@]}" >/dev/null; then
    if [[ "$ID" == 'ubuntu' ]]; then
        install_ubuntu_repository_fallback "$stage"
    fi

    fail 'The Docker CE package transaction cannot be resolved safely.'
fi

stage='docker_ce_package_download'
log 'Downloading Docker Engine, Buildx, and Docker Compose V2 from the official Docker repository.'
apt_get install -y --download-only --no-install-recommends \
    "${official_docker_packages[@]}"

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
    if package_is_installed "$package"; then
        installed_conflicts+=("$package")
    fi
done

if (( ${#installed_conflicts[@]} > 0 )); then
    stage='remove_conflicting_packages'
    log 'Removing packages that conflict with Docker CE.'
    apt_get remove -y "${installed_conflicts[@]}"
fi

stage='install_docker_ce'
log 'Installing the downloaded Docker CE package set.'
apt_get install -y --no-download --no-install-recommends \
    "${official_docker_packages[@]}"

verify_docker_stack

trap - ERR

log 'Docker installation completed successfully.'
