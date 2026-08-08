<?php

declare(strict_types=1);

namespace App\Infrastructure\Docker\Services;

use App\Domain\Server\DTOs\DockerRuntimeData;
use App\Domain\Server\Parsers\DockerContainerParser;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class DockerInspector
{
    private const string MARKER_AVAILABLE =
        '__xdeploy_docker__:available';

    private const string MARKER_NOT_INSTALLED =
        '__xdeploy_docker__:not_installed';

    private const string MARKER_UNAVAILABLE =
        '__xdeploy_docker__:unavailable';

    /**
     * Discover Docker and containers with one SSH command.
     *
     * Direct Docker access is attempted first. If the SSH user is not a
     * member of the docker group, xDeploy falls back to passwordless sudo,
     * which is already part of the supported server readiness contract.
     */
    private const string DISCOVERY_COMMAND = <<<'BASH'
if ! command -v docker >/dev/null 2>&1; then
    printf '__xdeploy_docker__:not_installed\n'
    exit 0
fi

docker_output="$(docker ps -a --format '{{json .}}' 2>/dev/null)"
docker_exit=$?

if [ "$docker_exit" -eq 0 ]; then
    printf '__xdeploy_docker__:available\n'
    printf '%s\n' "$docker_output"
    exit 0
fi

docker_output="$(sudo -n docker ps -a --format '{{json .}}' 2>/dev/null)"
docker_exit=$?

if [ "$docker_exit" -eq 0 ]; then
    printf '__xdeploy_docker__:available\n'
    printf '%s\n' "$docker_output"
    exit 0
fi

printf '__xdeploy_docker__:unavailable\n'
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private DockerContainerParser $parser,
    ) {}

    public function inspect(): DockerRuntimeData
    {
        $result = $this->ssh
            ->executeWithResult(
                self::DISCOVERY_COMMAND,
                SSHTimeout::NORMAL,
            );

        $lines = preg_split(
            '/\R/',
            trim(
                $result->output,
            ),
        );

        if (
            $lines === false
            || $lines === []
        ) {
            return DockerRuntimeData::unavailable();
        }

        $marker = trim(
            (string) array_shift(
                $lines,
            ),
        );

        if (
            $marker
            === self::MARKER_NOT_INSTALLED
        ) {
            return DockerRuntimeData::notInstalled();
        }

        if (
            $marker
            === self::MARKER_UNAVAILABLE
        ) {
            return DockerRuntimeData::unavailable();
        }

        if (
            $marker
            !== self::MARKER_AVAILABLE
        ) {
            return DockerRuntimeData::unavailable();
        }

        return DockerRuntimeData::available(
            $this->parser->parse(
                implode(
                    "\n",
                    $lines,
                ),
            ),
        );
    }
}
