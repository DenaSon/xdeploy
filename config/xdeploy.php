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

            'ubuntu' => [

                'path' => 'docker/ubuntu.sh',

                'sha256' => env(
                    'XDEPLOY_DOCKER_UBUNTU_SHA256',
                    'ecf7b0a8974a2b1e2569b843082a07d52658430ff3fa818d8776c73ee268de5c',
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
