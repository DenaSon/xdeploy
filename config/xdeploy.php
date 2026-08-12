<?php

declare(strict_types=1);

return [

    'ssh' => [
        'allow_private_targets' => env(
            'XDEPLOY_SSH_ALLOW_PRIVATE_TARGETS',
            false,
        ),
    ],

    'installers' => [

        'source' => env(
            'XDEPLOY_INSTALLER_SOURCE',
            env(
                'APP_ENV',
                'production',
            ) === 'production'
                ? 'http'
                : 'local',
        ),

        'local_root' => public_path(
            'assets/installers',
        ),

        'base_url' => env(
            'XDEPLOY_INSTALLER_BASE_URL',
            rtrim(
                (string) env(
                    'APP_URL',
                    '',
                ),
                '/',
            ).'/assets/installers',
        ),

        'docker' => [

            'debian_family' => [

                'path' => 'docker/debian-family.sh',

                'sha256' => env(
                    'XDEPLOY_DOCKER_DEBIAN_FAMILY_SHA256',
                    '0e794bfcd221def8f5e1f321c87e523ba97059e2b084b174aebeaed90985e87d',
                ),

            ],

        ],

        'caddy' => [

            'debian_family' => [

                'path' => 'caddy/debian-family.sh',

                'sha256' => env(
                    'XDEPLOY_CADDY_DEBIAN_FAMILY_SHA256',
                    '84bcc5941b92efdf1bab1a0006150e79db618749b75717ffba4e5a2be121bce5',
                ),

            ],

        ],

        'marzban' => [

            'ubuntu' => [

                'path' => 'marzban/ubuntu.sh',

                'sha256' => env(
                    'XDEPLOY_MARZBAN_UBUNTU_SHA256',
                    '619961db4b87635ead20cd194446dfbb8dff57806348710b9e7aab6d5a2d1b70',
                ),

            ],

        ],

        'n8n' => [

            'docker' => [

                'path' => 'n8n/docker.sh',

                'sha256' => env(
                    'XDEPLOY_N8N_DOCKER_SHA256',
                    '2fc78cf0cecdaa8f47655b9b25d339755a7cd86552dd024295ecca9e9d340222',
                ),

            ],

        ],

    ],

];
