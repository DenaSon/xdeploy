<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installers;

use Tests\TestCase;

final class AmneziaWgInstallerAssetTest extends TestCase
{
    public function test_amneziawg_installer_matches_the_pinned_checksum(): void
    {
        $path = public_path(
            'assets/installers/amneziawg/docker.sh',
        );

        $this->assertFileExists(
            $path,
        );

        $this->assertSame(
            (string) config(
                'xdeploy.installers.amneziawg.docker.sha256',
            ),
            hash_file(
                'sha256',
                $path,
            ),
        );
    }

    public function test_amneziawg_installer_uses_the_pinned_awg2_image_and_minimum_privileges(): void
    {
        $contents = $this->installerContents();

        $this->assertStringContainsString(
            'amneziavpn/amneziawg-go:0.2.19@sha256:acef5ae84808a9568448e9d8c7a96f640a5ccc590b0f8dfbc2df9f9dc0e848c9',
            $contents,
        );
        $this->assertStringContainsString(
            'cap_drop:',
            $contents,
        );
        $this->assertStringContainsString(
            '- ALL',
            $contents,
        );
        $this->assertStringContainsString(
            'cap_add:',
            $contents,
        );
        $this->assertStringContainsString(
            '- NET_ADMIN',
            $contents,
        );
        $this->assertStringContainsString(
            '/dev/net/tun:/dev/net/tun',
            $contents,
        );
        $this->assertStringContainsString(
            'net.ipv4.conf.all.src_valid_mark',
            $contents,
        );
        $this->assertStringContainsString(
            'net.ipv4.ip_forward',
            $contents,
        );
        $this->assertStringNotContainsString(
            'privileged:',
            $contents,
        );
        $this->assertStringNotContainsString(
            'SYS_MODULE',
            $contents,
        );
        $this->assertStringNotContainsString(
            '/lib/modules',
            $contents,
        );
    }

    public function test_amneziawg_installer_persists_runtime_state_and_verifies_awg_before_marking_complete(): void
    {
        $contents = $this->installerContents();

        $this->assertStringContainsString(
            'readonly DATA_DIR="$APP_DIR/data"',
            $contents,
        );
        $this->assertStringContainsString(
            './data:/opt/amnezia/awg',
            $contents,
        );
        $this->assertStringContainsString(
            'readonly CONTAINER_NAME="amnezia-awg2"',
            $contents,
        );
        $this->assertStringContainsString(
            'awg show awg0',
            $contents,
        );
        $this->assertStringContainsString(
            'touch "$MARKER_FILE"',
            $contents,
        );
        $this->assertStringContainsString(
            "stage='runtime_verify'",
            $contents,
        );
    }

    private function installerContents(): string
    {
        $contents = file_get_contents(
            public_path(
                'assets/installers/amneziawg/docker.sh',
            ),
        );

        $this->assertIsString(
            $contents,
        );

        return $contents;
    }
}
