# Media-node deployment

The media node receives RTMP, serves HLS, records temporary segments, and
communicates authenticated recording metadata to the core. It consists of
MediaMTX, the local webhook, the worker, and the initialization service.

## Requirements

- Docker Engine and Compose v2;
- HTTPS access to the core;
- shared S3/MinIO bucket access;
- local disk for pending recording segments;
- private access to MediaMTX API port 9997.

Open 1935/TCP for RTMP and 8888/TCP for HLS. Open 8554/TCP, 8889/TCP, or
8189/UDP only when RTSP or WebRTC is explicitly used. The API on 9997 must be
reachable only by the core, preferably over a VPN.

## Environment

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

Start the node with:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml up -d --build
docker compose --env-file .env.media-node -f docker-compose.media-node.yml ps
docker compose --env-file .env.media-node -f docker-compose.media-node.yml logs -f mediamtx media-webhook media-worker
```

## Functional test

Create a transmission in the core, configure OBS with the supplied RTMP URL,
and confirm the state changes to `live`. Close the segment, verify the object
in the shared bucket, and confirm that the core creates the approved media
record. Retry failed jobs with `queue:retry`; the recording path is derived
from the checksum and is idempotent.

For a complete test, also open the public page, validate comments, and inspect
`media-webhook` and `media-worker` logs while a segment closes. Pending files
remain in the recordings volume until both storage and core acknowledge them.

## Security

Keep `.env.media-node` outside Git, use a private network or VPN between core,
node, and storage, restrict MinIO to authorized workers, and rotate the worker
token and storage credentials after a suspected leak.

Back up the recordings and state volumes. Do not put real addresses or
credentials in documentation; use per-installation environment files.
