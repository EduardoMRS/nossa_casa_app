# Transmissions

This guide covers creating, publishing, monitoring, moderating, and stopping
a transmission. Core deployment is documented in
[media-core-deployment.md](media-core-deployment.md); a separate media node is
documented in [media-node-deployment.md](media-node-deployment.md).

## Flow

1. A media administrator creates a transmission in the dashboard.
2. The dashboard provides the OBS ingest URL and publisher token.
3. OBS publishes RTMP to MediaMTX.
4. MediaMTX exposes HLS to viewers and creates recording segments when enabled.
5. The media worker uploads segments to shared storage and sends metadata to
   the core.

The canonical public page is `/live-streams/{id}`. The canonical dashboard page
is `/dashboard/live-streams`.

Each church may keep up to two live links active at the same time. A link can
be configured to finish automatically when OBS stops publishing; otherwise an
authorized operator finishes it from the dashboard. Recordings continue to be
processed after the public page changes to offline.

## Ports and permissions

| Port | Use | Exposure |
|---|---|---|
| 80/443 | application and HLS reverse proxy | public |
| 1935/TCP | OBS RTMP ingest | public when required |
| 8888/TCP | direct HLS | private when proxied |
| 9997/TCP | MediaMTX API | private core-to-node network |

Visitors can watch and read comments. Authenticated church members can
comment. `media`, `church_leader`, `superadmin`, and `system` users can manage
transmissions according to their church scope.

## OBS

Use the complete ingest URL supplied by the dashboard as the OBS server and
leave the stream key empty when the token is already part of the URL. Use H.264
video, AAC audio, and a two-second keyframe interval for broad browser and HLS
compatibility.

Before an event, test video, audio, the public page, an authenticated comment,
moderation permissions, disk space, and the media worker. Never publish the
ingest URL or token in a public channel. Renew the token from the dashboard if
it may have leaked; OBS must then be configured with the new URL.

## Lifecycle

When MediaMTX accepts OBS, the app changes the transmission to `live` and the
dashboard polls its state. Stop OBS first, wait for the offline event, and then
stop the transmission from the dashboard. Recording segments can finish after
the page is marked offline.

## Troubleshooting

- If OBS cannot connect, verify the current ingest URL, empty stream-key field,
  token, and TCP port 1935.
- If the state does not become live, inspect MediaMTX and core logs and verify
  `MEDIA_WORKER_TOKEN` on both sides.
- If the player does not open, verify `MEDIAMTX_PUBLIC_HLS_URL`, HTTPS, CORS,
  and the reverse proxy to port 8888.
- If a recording is missing, inspect `media-worker`, failed jobs, local disk,
  and shared storage availability.

Useful recovery commands on the media node are:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:failed
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:retry all
```

Recording jobs are idempotent and use the checksum-derived path, so retrying a
job does not create duplicate files. Keep the MediaMTX API private and use
HTTPS for HLS, core callbacks, and storage whenever traffic crosses an
untrusted network.
