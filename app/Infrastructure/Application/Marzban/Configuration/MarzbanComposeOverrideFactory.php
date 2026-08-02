<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Configuration;

final readonly class MarzbanComposeOverrideFactory
{
    public function make(): string
    {
        return <<<'YAML'
# xDeploy: marzban-https
services:
  caddy:
    image: caddy:2-alpine
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/lib/marzban:/var/lib/marzban
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config

volumes:
  caddy_data:
  caddy_config:
YAML;
    }
}
