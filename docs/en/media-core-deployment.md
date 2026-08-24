# Core and storage deployment

The core maintains users, churches, permissions, transmissions, dashboard
pages, and recording metadata. It controls MediaMTX through its private API;
video files are stored directly by the media worker and are not uploaded
through Laravel.

## Core environment

```dotenv
MEDIA_NODE_ROLE=core
MEDIA_WORKER_TOKEN=the-shared-random-token
MEDIAMTX_API_URL=http://mediamtx:9997
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://stream.example.com:1935
MEDIAMTX_PUBLIC_HLS_URL=https://stream.example.com/hls
MEDIA_ARCHIVE_DISK=minio
MEDIA_DISK=minio
MINIO_BUCKET=nossa-casa
MINIO_ENDPOINT=http://minio:9000
MINIO_TEMPORARY_URL=https://storage.example.com
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

`MEDIAMTX_RECORD_SEGMENT_DURATION` controls segment closing time. A shorter
value is useful during development. `MINIO_ENDPOINT` is the container-to-S3
endpoint, while `MINIO_TEMPORARY_URL` is the browser-facing base URL; they may
be different. Keep all placeholders installation-specific and out of Git.

Inside the default Compose environment, the API is `http://mediamtx:9997` and
the storage endpoint is `http://minio:9000`. With a remote media node, replace
the MediaMTX API URL with its private address and keep the shared storage
reachable by both core and worker.

## Compose modes

For an all-in-one environment:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

For a core without local media services, use the core Compose override and set
`REMOTE_MEDIAMTX_API_URL` to the private media-node address. The media-node
must use the same `MEDIA_WORKER_TOKEN`, bucket, and logical archive disk.

The default development Compose stack also starts MinIO and its bucket
initializer. From the host, MinIO is normally available at ports 9000 (S3 API)
and 9001 (console); expose the console only to administrators. For a remote
node, allow the private S3 API from that node and never use `127.0.0.1` in its
environment.

## Validation

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list --path=api/internal/media
docker compose exec app php artisan queue:failed
```

Validate storage from inside the app container and validate the MediaMTX API
from the core host. Keep the MediaMTX API, MinIO API, and MinIO console off the
public internet. Use HTTPS whenever traffic crosses an untrusted network.

The integrated flow is healthy when the core can create/remove MediaMTX paths,
receive online/offline callbacks, and confirm a recording by checksum and
size. The internal recording endpoint receives JSON metadata only; video is
written directly by the worker to the shared bucket. Configure backups,
retention, queue monitoring, and dedicated least-privilege S3 credentials for
production.
