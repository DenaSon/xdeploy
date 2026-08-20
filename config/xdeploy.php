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
                    '2261fc24cdf1c862e2ba30adab62a1de1ad8bd977a2de007b65075736293674e',
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
                    '1607dd0eed65caacc2a05ff8ea9ecbce9370e1df9c4c4a908cd955e542cf6fe3',
                ),

            ],

        ],

        'amneziawg' => [

            'docker' => [

                'path' => 'amneziawg/docker.sh',

                'sha256' => env(
                    'XDEPLOY_AMNEZIAWG_DOCKER_SHA256',
                    '35fdd8483ac0f464142483c9d0fe92c4dfb2afb7b2c8865168f521743d26a299',
                ),

            ],

        ],

        'wordpress' => [

            'docker' => [

                'path' => 'wordpress/docker.sh',

                'sha256' => env(
                    'XDEPLOY_WORDPRESS_DOCKER_SHA256',
                    'a22256e1740869383aaeaf7e695d2e915925fdbf93bd7ae6ec493085b97e8ff5',
                ),

            ],

        ],

    ],

];
