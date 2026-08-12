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
                    '95e94552d184155bd9f385c5750538350f28a4ae2f807b19d601a9d16fc34a91',
                ),

            ],

        ],

        'caddy' => [

            'debian_family' => [

                'path' => 'caddy/debian-family.sh',

                'sha256' => env(
                    'XDEPLOY_CADDY_DEBIAN_FAMILY_SHA256',
                    '9f0c4df4a95263b131371959e027a2e6281e663333879902e73a6a5846582aff',
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

    ],

];
