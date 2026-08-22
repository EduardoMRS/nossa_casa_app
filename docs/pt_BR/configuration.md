# Configuração do core e do media-node

## Aplicação core

O `.env` do core controla Laravel, banco, storage e a API privada do MediaMTX:

```dotenv
MEDIA_NODE_ROLE=core
MEDIA_WORKER_ID=core-worker
MEDIA_WORKER_TOKEN=use-o-mesmo-token-aleatorio-no-core-e-no-media-node
MEDIAMTX_API_URL=http://mediamtx:9997
MEDIAMTX_API_TOKEN=token-opcional-da-api
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://stream.example.com:1935
MEDIAMTX_PUBLIC_HLS_URL=https://stream.example.com/hls
MEDIAMTX_RECORD_SEGMENT_DURATION=15m
MEDIA_ARCHIVE_DISK=minio
MEDIA_DISK=minio
MINIO_ACCESS_KEY_ID=substitua
MINIO_SECRET_ACCESS_KEY=substitua
MINIO_BUCKET=nossa-casa
MINIO_ENDPOINT=http://minio:9000
MINIO_TEMPORARY_URL=https://storage.example.com
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

`MINIO_ENDPOINT` é usado pelos containers. `MINIO_TEMPORARY_URL` é entregue ao navegador e precisa ser acessível pelos clientes.

## Media-node

O media-node recebe RTMP, entrega HLS, armazena gravações temporárias e envia metadados autenticados ao core. O `.env.media-node` deve conter:

```dotenv
APP_KEY=base64:substitua-por-uma-chave-aleatoria
MEDIA_WORKER_ID=media-node-1
MEDIA_WORKER_TOKEN=o-mesmo-token-do-core
MEDIA_CORE_URL=https://app.example.com
MEDIA_CORE_VERIFY_TLS=true
MEDIA_CORE_CONNECT_TIMEOUT=10
MEDIA_CORE_REQUEST_TIMEOUT=30
MEDIA_ARCHIVE_DISK=minio
MINIO_ACCESS_KEY_ID=substitua
MINIO_SECRET_ACCESS_KEY=substitua
MINIO_BUCKET=nossa-casa
MINIO_ENDPOINT=https://storage.internal.example.com
MINIO_USE_PATH_STYLE_ENDPOINT=true
MEDIA_API_BIND_IP=10.0.0.20
MEDIAMTX_API_PORT=9997
MEDIAMTX_HLS_PORT=8888
MEDIAMTX_RTMP_PORT=1935
```

O media-node deve alcançar o core por HTTPS e o storage pela rede privada. Mantenha o token do worker com pelo menos 64 caracteres aleatórios.

## Regras de rede

- publique 80/443 para a aplicação e o proxy HLS;
- publique 1935/TCP para o OBS quando RTMP for usado;
- mantenha 9997/TCP entre core e media-node em rede privada;
- mantenha MinIO 9000 e console 9001 privados;
- abra portas WebRTC somente quando esse recurso estiver habilitado.
