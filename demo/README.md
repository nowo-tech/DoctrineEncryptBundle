# Doctrine Encrypt Bundle — Demos

Each demo is a minimal Symfony app with **FrankenPHP**, its own **Caddyfile**, Docker, and a Makefile.

By default **`docker-compose`** uses **`APP_ENV=dev`**. Runtime mode is controlled by **`FRANKENPHP_MODE`** (`classic` \| `worker`, default **`worker`** in `.env.example`). See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).

| Demo     | Default port | PHP |
|----------|--------------|-----|
| symfony8 | 8008         | 8.4 |

## Requirements

- Docker and Docker Compose
- This bundle repository cloned (demos mount the bundle from `../..` as `/var/doctrine-encrypt-bundle`)

## Quick start

From the demo directory (`demo/symfony8`):

```bash
make up        # Start the FrankenPHP container
make install   # composer install inside the container
make setup     # install + create DB + schema + generate encryption key
```

Then open the URL printed by `make up` (e.g. `http://localhost:8008`).

## Make targets

- **up** – Start containers (FrankenPHP serves HTTP on the configured port).
- **down** – Stop containers.
- **build** – Rebuild the image without cache.
- **install** – `composer install` in the container (bundle from `/var/doctrine-encrypt-bundle`).
- **setup** – install + `doctrine:database:create` + `doctrine:schema:update` + `doctrine:encrypt:generate-secret-key` (creates key files and adds `APP_ENCRYPT_KEY` to `.env` when missing).
- **shell** – Open a shell in the PHP container.
- **logs** – Show container logs.
- **db-create** – Create the SQLite database.
- **db-schema** – Update the Doctrine schema.
- **key** – Generate keys: `personal_data` uses `APP_ENCRYPT_KEY` (printed; add it to `.env`); `financial_data` uses `.demo_financial.key`.
- **cache-clear** – Clear the Symfony cache.
- **update-bundle** – Refresh the path-mounted bundle and clear cache.
- **test** – Run demo tests (when present).

## Change port

In the demo directory:

```bash
PORT=9008 make up   # Symfony 8 at http://localhost:9008
```

## Demo layout

- **Dockerfile** – FrankenPHP (Alpine), extensions `zip`, `intl`, `sodium`, `pdo_sqlite`, custom Caddyfile.
- **docker/frankenphp/Caddyfile** – Production: worker on `index.php`. **Caddyfile.dev** – classic mode (no worker).
- **docker-compose.yml** – `php` service, mounts demo code and the bundle (`../..` → `/var/doctrine-encrypt-bundle`), plus `Caddyfile.dev` / `php-dev.ini` for development.
- **Makefile** – Targets listed above.
- **public/index.php** – Symfony front controller.

The bundle is installed via a Composer **path repository** pointing at `/var/doctrine-encrypt-bundle` (mounted from the bundle repo root).
