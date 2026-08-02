# xDeploy — Sprint 8.3.4

This package completes the safe Marzban HTTPS activation workflow.

## Scope

- Re-inspect the current HTTPS state before activation.
- Re-run DNS, port and installation-layout preflight immediately before any
  mutation.
- Refuse to overwrite externally managed or ambiguous HTTPS configuration.
- Build a Caddyfile and an xDeploy-owned Docker Compose override.
- Update only the whitelisted `.env` keys:
  - `UVICORN_UDS`
  - `XRAY_SUBSCRIPTION_URL_PREFIX`
- Validate the combined Compose candidate with `docker compose config`.
- Validate the Caddy candidate with the official Caddy container.
- Create temporary timestamped backups of every affected existing file.
- Atomically replace the managed files and start the combined stack.
- Verify Marzban, Caddy, port 443, the trusted TLS certificate and the final
  dashboard URL.
- Restore the previous files and runtime when a post-mutation step fails.
- Serialize HTTPS mutations on the VPS with `flock`.
- Return sanitized failure and recovery states to the Persian Livewire UI.

## Managed remote files

The base `/opt/marzban/docker-compose.yml` is never rewritten. xDeploy owns
only these HTTPS files and values:

- `/opt/marzban/docker-compose.xdeploy.yml`
- `/opt/marzban/Caddyfile`
- the two whitelisted keys in `/opt/marzban/.env`

The Compose override keeps the user's base Compose services and values intact.
Candidate files stay temporary and original files are not changed unless every
candidate validation passes.

## Failure semantics

- Candidate failure: no original file is changed.
- Mutation or verification failure: limited compensation restores the previous
  files and restarts the previous Marzban stack.
- Recovery failure: success is never reported and the UI instructs the user to
  stop retrying until the server is inspected manually.
- External reverse proxy or non-disabled HTTPS state: xDeploy refuses to
  overwrite it.

This is a Marzban-specific compensation workflow, not a generic rollback
engine.

## Install

Extract the package in the xDeploy project root and merge the `app`,
`resources` and `tests` directories. No migration or new service-provider
binding is required; the existing `MarzbanHttpsGateway` binding remains valid
and the two configuration factories are auto-wired by Laravel.

Run:

```powershell
php vendor/bin/pint

php artisan optimize:clear

php vendor/bin/phpunit tests/Unit/Application/Applications/Marzban/EnableMarzbanHttpsActionTest.php

php vendor/bin/phpunit tests/Unit/Infrastructure/Application/Marzban/MarzbanHttpsConfigurationFactoryTest.php

php vendor/bin/phpunit tests/Unit/Infrastructure/Application/Marzban/SshMarzbanHttpsGatewayApplyTest.php

composer test
```

## Real VPS acceptance test

1. Use a direct DNS-only A record pointing to the VPS public IPv4 address.
2. Open the Marzban management page and run the readiness check.
3. Confirm that DNS, ports 80/443 and the Marzban layout are ready.
4. Click `فعال‌سازی HTTPS` and confirm the mutation warning.
5. Wait for Caddy to obtain the certificate and for final verification.
6. Confirm that the management snapshot shows `HTTPS: فعال` and that the
   displayed dashboard URL opens with a trusted certificate.

For the failure path, use only a disposable VPS: make the public HTTPS probe
fail after candidate validation and confirm that the UI reports successful
recovery, Marzban remains running and no false success is shown.

## Intentional exclusions

- Cloudflare API or proxied-record management
- wildcard or multiple domains
- adoption or replacement of an external reverse proxy
- certificate persistence in the xDeploy database
- background jobs, progress streaming or a generic deployment engine
