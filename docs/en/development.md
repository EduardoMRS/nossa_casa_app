# Development and deployment

## Requirements

The supported development and production workflow uses Docker Compose. The
same repository can be used on Windows, Linux, and macOS when Docker Desktop
or Docker Engine with Compose v2 is available.

- Docker Desktop (Windows or macOS), or Docker Engine plus the Compose plugin
  (Linux).
- Git.
- At least 4 GB of memory available for the application, database, storage,
  MediaMTX, and worker containers.
- Ports 80, 1935, 5173, 8080, 8189/udp, 8888, 9000, 9001, and 9997 available
  according to the services being used.

Windows users should use WSL2 and keep the repository in the Linux filesystem
when file watching is slow. macOS users should grant Docker Desktop access to
the repository directory. Linux users should add their account to the
`docker` group or use `sudo` with Docker commands.

## Development environment

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app npm run build
```

Use `docker compose logs -f app db media-worker mediamtx` to inspect startup
and media processing. The application is available through the webserver on
port 80. The Vite development server can be started with:

```bash
docker compose exec app npm run dev -- --host 0.0.0.0
```

Do not use `DB_HOST=127.0.0.1` inside the app container. The Compose service
name is `db`.

## Production environment

Create a deployment-specific `.env` outside Git and set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
APP_KEY=base64:replace-with-a-random-key
DOCKER_DB_PASSWORD=replace-with-a-strong-password
MINIO_ROOT_USER=replace-with-a-dedicated-user
MINIO_ROOT_PASSWORD=replace-with-a-strong-password
```

Start and migrate the environment:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app npm run build
```

Put HTTPS and firewall rules in front of the webserver. Do not expose the
MediaMTX API (`9997`) or the MinIO console (`9001`) to the public internet.
Back up the database and storage volumes before changing images or removing
Compose volumes.

## Environment parity

Development and production use the same service names and Compose topology.
Only secrets, public URLs, debug settings, storage endpoints, and firewall
rules should differ. The media-node can run on another machine using the
separate Compose file documented in [Configuration](configuration.md).
