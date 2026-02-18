# Demo Projects

The bundle includes three **dockerized** demo projects, one per supported Symfony version. Each runs on **FrankenPHP** with a custom **Caddyfile** and has its own **Makefile**.

## Screenshots

**Demo home** – Overview, CRUD link and console commands:

![Doctrine Encrypt Bundle Demo – home](assets/demo-home.png)

**Secret messages (CRUD)** – List of messages (encrypted in DB with Halite), create/edit/delete:

![Secret messages CRUD](assets/demo-crud.png)

| Demo      | Path            | Default port | PHP |
|-----------|-----------------|--------------|-----|
| Symfony 6 | `demo/symfony6/` | 8006         | 8.2 |
| Symfony 7 | `demo/symfony7/` | 8007         | 8.2 |
| Symfony 8 | `demo/symfony8/` | 8008         | 8.4 |

## Quick start (Docker)

From the **demo directory** (e.g. `demo/symfony8`):

```bash
make up        # Start FrankenPHP container
make install   # composer install (bundle from /var/doctrine-encrypt-bundle)
make setup     # install + DB create + schema + generate secret key
```

Then open **http://localhost:8008** (or the port shown by `make up`).

## Makefile targets

- **up** – Start containers (FrankenPHP HTTP on configured port).
- **down** – Stop containers.
- **build** – Rebuild image (no cache).
- **install** – `composer install` in container.
- **setup** – install + `doctrine:database:create` + `doctrine:schema:update` + `doctrine:encrypt:generate-secret-key`.
- **shell** – Shell in PHP container.
- **logs** – Container logs.
- **db-create**, **db-schema**, **key** – Database and encryption key.
- **cache-clear**, **update-bundle**, **test** – Cache, bundle update, tests.

Change port: `PORT=9008 make up`.

## What each demo includes

- **Dockerfile** – FrankenPHP (Alpine), extensions: zip, intl, sodium, pdo_sqlite; custom Caddyfile.
- **docker/frankenphp/Caddyfile** – Root `/app/public`, worker `index.php`, encoding (zstd, br, gzip).
- **docker-compose.yml** – Service `php`, volumes: demo dir + bundle root as `/var/doctrine-encrypt-bundle`.
- **Makefile** – Targets above.
- **public/index.php** – Symfony front controller.
- **config/** – Bundles (DoctrineEncryptBundle), Doctrine, `nowo_doctrine_encrypt.yaml`.
- **src/Entity/SecretMessage.php** – Example entity with `#[Encrypted]` property.
- **src/Controller/DemoController.php** – Simple home route.

The bundle is installed via Composer **path repository** pointing to `/var/doctrine-encrypt-bundle` (mounted from the repo root). See **demo/README.md** for more detail.
