# Doctrine Encrypt Bundle – Demos

Cada demo es una app Symfony mínima con **FrankenPHP** y un **Caddyfile** propio, dockerizada y con Makefile.

| Demo        | Puerto por defecto | PHP   |
|------------|--------------------|-------|
| symfony6   | 8006               | 8.2   |
| symfony7   | 8007               | 8.2   |
| symfony8   | 8008               | 8.4   |

## Requisitos

- Docker y Docker Compose
- El repo del bundle clonado (las demos montan el bundle desde `../..` como `/var/doctrine-encrypt-bundle`)

## Uso rápido

Desde la carpeta de cada demo (por ejemplo `demo/symfony8`):

```bash
make up        # Levanta el contenedor (FrankenPHP + Caddy)
make install   # composer install dentro del contenedor
make setup     # install + crear DB + schema + generar clave de cifrado
```

Luego abre en el navegador la URL que se indique (ej. `http://localhost:8008`).

## Comandos Make

- **up** – Levanta los contenedores (FrankenPHP sirve HTTP en el puerto indicado).
- **down** – Para los contenedores.
- **build** – Reconstruye la imagen sin caché.
- **install** – `composer install` en el contenedor (usa el bundle desde `/var/doctrine-encrypt-bundle`).
- **setup** – install + `doctrine:database:create` + `doctrine:schema:update` + `doctrine:encrypt:generate-secret-key`.
- **shell** – Abre una shell en el contenedor PHP.
- **logs** – Muestra los logs del contenedor.
- **db-create** – Crea la base SQLite.
- **db-schema** – Actualiza el schema Doctrine.
- **key** – Genera la clave secreta de cifrado (Halite).
- **cache-clear** – Limpia la caché de Symfony.
- **update-bundle** – Actualiza el bundle desde el path montado y limpia caché.
- **test** – Ejecuta los tests de la demo (si existen).

## Cambiar puerto

En la carpeta de la demo:

```bash
PORT=9008 make up   # Symfony 8 en http://localhost:9008
```

## Estructura de cada demo

- **Dockerfile** – Imagen FrankenPHP (Alpine), extensiones `zip`, `intl`, `sodium`, `pdo_sqlite`, Caddyfile custom.
- **docker/frankenphp/Caddyfile** – Caddy: raíz en `/app/public`, worker `index.php`, compresión.
- **docker-compose.yml** – Servicio `php`, montaje del código de la demo y del bundle (`../..` → `/var/doctrine-encrypt-bundle`).
- **Makefile** – Objetivos anteriores.
- **public/index.php** – Front controller de Symfony.

El bundle se instala por **path repository** apuntando a `/var/doctrine-encrypt-bundle` (montado desde la raíz del repo del bundle).
