# Core and media-node configuration

## Core application

The core `.env` controls the Laravel application, database, storage, and the
private MediaMTX API:

```dotenv
MEDIA_NODE_ROLE=core
MEDIA_WORKER_ID=core-worker
MEDIA_WORKER_TOKEN=use-the-same-random-token-on-core-and-media-node

MEDIAMTX_API_URL=http://mediamtx:9997
MEDIAMTX_API_TOKEN=optional-api-token
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://stream.example.com:1935
MEDIAMTX_PUBLIC_HLS_URL=https://stream.example.com/hls
MEDIAMTX_RECORD_SEGMENT_DURATION=15m

MEDIA_ARCHIVE_DISK=minio
MEDIA_DISK=minio
MINIO_ACCESS_KEY_ID=replace-me
MINIO_SECRET_ACCESS_KEY=replace-me
MINIO_BUCKET=nossa-casa
MINIO_ENDPOINT=http://minio:9000
MINIO_TEMPORARY_URL=https://storage.example.com
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

`MINIO_ENDPOINT` is used by containers. `MINIO_TEMPORARY_URL` is delivered to
browsers and must be reachable by clients. They may be different URLs.

## Media node

The media-node receives RTMP, serves HLS, stores temporary recordings, and
sends authenticated metadata to the core. Its `.env.media-node` should contain:

```dotenv
APP_KEY=base64:replace-with-a-random-key
MEDIA_WORKER_ID=media-node-1
MEDIA_WORKER_TOKEN=the-same-token-as-the-core

MEDIA_CORE_URL=https://app.example.com
MEDIA_CORE_VERIFY_TLS=true
MEDIA_CORE_CONNECT_TIMEOUT=10
MEDIA_CORE_REQUEST_TIMEOUT=30

MEDIA_ARCHIVE_DISK=minio
MINIO_ACCESS_KEY_ID=replace-me
MINIO_SECRET_ACCESS_KEY=replace-me
MINIO_BUCKET=nossa-casa
MINIO_ENDPOINT=https://storage.internal.example.com
MINIO_USE_PATH_STYLE_ENDPOINT=true

MEDIA_API_BIND_IP=10.0.0.20
MEDIAMTX_API_PORT=9997
MEDIAMTX_HLS_PORT=8888
MEDIAMTX_RTMP_PORT=1935
```

The media-node must reach the core over HTTPS and the shared storage over the
private network. Keep the worker token at least 64 random characters and
rotate it after a suspected leak.

## Network rules

- Publish 80/443 for the application and HLS reverse proxy.
- Publish 1935/TCP for OBS when RTMP ingest is required.
- Keep 9997/TCP private between the core and media-node.
- Keep MinIO 9000 and its console 9001 private.
- Open WebRTC ports only when WebRTC is enabled.
