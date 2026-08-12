<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Platform\Caddy\Configuration\CaddySiteConfigurationFactory;
use App\Support\SSH\SSHTimeout;
use RuntimeException;

final readonly class SshCaddySiteReader implements CaddySiteReaderInterface
{
    private const int SITE_ABSENT = 3;

    private const string ENVIRONMENT_COMMAND = <<<'BASH'
root='/etc/caddy/Caddyfile'
managed_root='/etc/caddy/xdeploy'
sites_dir="$managed_root/sites"
managed_marker='# xDeploy: caddy-platform'
managed_import='import xdeploy/sites/*.caddy'

if [ ! -r "$root" ] ||
    [ -L "$root" ] ||
    [ ! -d "$managed_root" ] ||
    [ -L "$managed_root" ] ||
    [ ! -d "$sites_dir" ] ||
    [ -L "$sites_dir" ]; then
    exit 1
fi

grep -Fxq "$managed_marker" "$root" &&
grep -Fxq "$managed_import" "$root"
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
        private CaddySiteConfigurationFactory $configurationFactory,
    ) {}

    public function environmentReady(): bool
    {
        $result = $this->privileged->executeWithResult(
            command: self::ENVIRONMENT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        return $result->successful();
    }

    public function exists(
        CaddySiteKey $key,
    ): bool {
        $siteFile = $this->siteFile($key);

        $result = $this->privileged->executeWithResult(
            command: sprintf(
                <<<'BASH'
site_file=%s

if [ -e "$site_file" ] || [ -L "$site_file" ]; then
    exit 0
fi

exit 3
BASH,
                escapeshellarg($siteFile),
            ),
            timeout: SSHTimeout::QUICK,
        );

        if ($result->successful()) {
            return true;
        }

        if ($result->exitCode === self::SITE_ABSENT) {
            return false;
        }

        throw new RuntimeException(
            'The xDeploy-managed Caddy site could not be inspected.',
        );
    }

    public function matches(
        CaddySite $site,
    ): bool {
        if (! $this->environmentReady()) {
            return false;
        }

        $siteFile = $this->siteFile($site->key);

        $result = $this->privileged->executeWithResult(
            command: sprintf(
                <<<'BASH'
site_file=%s

if [ ! -f "$site_file" ] ||
    [ ! -r "$site_file" ] ||
    [ -L "$site_file" ]; then
    exit 2
fi

cat "$site_file"
BASH,
                escapeshellarg($siteFile),
            ),
            timeout: SSHTimeout::QUICK,
        );

        if ($result->exitCode === 2) {
            return false;
        }

        if (! $result->successful()) {
            throw new RuntimeException(
                'The xDeploy-managed Caddy site could not be inspected.',
            );
        }

        return $this->normalize($result->output)
            === $this->normalize(
                $this->configurationFactory->make($site),
            );
    }

    private function siteFile(
        CaddySiteKey $key,
    ): string {
        return '/etc/caddy/xdeploy/sites/'.$key->filename();
    }

    private function normalize(string $content): string
    {
        return rtrim(
            str_replace("\r\n", "\n", $content),
            "\n",
        )."\n";
    }
}
