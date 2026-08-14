# Implantação do nó de mídia (MediaMTX)

Este guia cobre apenas a máquina que recebe, distribui e grava temporariamente as transmissões. A configuração do app principal e do storage compartilhado está em [Servidor principal e storage](media-core-deployment.md).

## Responsabilidades do nó

```text
OBS/câmera ──RTMP/RTSP──> MediaMTX ──HLS──> espectadores
                              │
                              ├── autenticação e estado ──HTTPS──> app principal
                              │
                              └── segmento temporário em /recordings
                                      │
                                 media-webhook
                                      │ fila SQLite
                                  media-worker
                                      ├── arquivo ──S3──> storage compartilhado
                                      └── metadados ──HTTPS──> app principal
```

O arquivo de vídeo não passa pelo app principal. O `media-worker` grava diretamente no disco configurado por `MEDIA_ARCHIVE_DISK`; depois, o app principal recebe somente os metadados autenticados e publica o objeto já existente. O segmento local é removido apenas após essas duas confirmações.

Os serviços de `docker-compose.media-node.yml` são:

- `mediamtx`: ingestão, HLS e gravação dos segmentos locais;
- `media-webhook`: recebe o webhook local do MediaMTX e cria o job;
- `media-worker`: envia o segmento ao storage compartilhado e registra a gravação no app;
- `media-init`: prepara o SQLite usado pela fila e termina com código `0`.

## Pré-requisitos e rede

- Docker Engine e Docker Compose;
- acesso HTTPS ao app principal;
- acesso S3 ao mesmo bucket utilizado pelo app principal;
- espaço local para reter segmentos enquanto o storage ou o app estiver indisponível;
- `1935/TCP` para RTMP e `8888/TCP` para HLS;
- `9997/TCP` acessível somente pelo servidor principal, preferencialmente via VPN.

Libere `8554/TCP`, `8889/TCP` e `8189/UDP` apenas se RTSP ou WebRTC forem usados. A API `9997` não deve ficar pública.

## Configurar o ambiente

Na máquina de mídia:

```bash
git clone <url-do-repositorio> nossa-casa-app
cd nossa-casa-app
cp .env.media-node.example .env.media-node
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

Edite `.env.media-node`. Os valores entre `<...>` são referências e devem ser substituídos:

```dotenv
APP_KEY=base64:<chave-gerada>
MEDIA_WORKER_ID=<identificador-unico-deste-no>
MEDIA_WORKER_TOKEN=<mesmo-token-longo-configurado-no-servidor-principal>

MEDIA_CORE_URL=https://<dominio-do-servidor-principal>
MEDIA_CORE_VERIFY_TLS=true
MEDIA_CORE_CONNECT_TIMEOUT=10
MEDIA_CORE_REQUEST_TIMEOUT=30

MEDIA_ARCHIVE_DISK=minio
MINIO_ACCESS_KEY_ID=<usuario-do-minio>
MINIO_SECRET_ACCESS_KEY=<senha-do-minio>
MINIO_DEFAULT_REGION=us-east-1
MINIO_BUCKET=<bucket-compartilhado>
MINIO_ENDPOINT=http://<ip-da-maquina-de-arquivos>:9000
MINIO_USE_PATH_STYLE_ENDPOINT=true

MEDIA_API_BIND_IP=<ip-privado-desta-maquina-de-midia>
```

O endpoint do MinIO deve ser alcançável de dentro do contêiner `media-worker`. Não use `127.0.0.1` para um MinIO executado em outra máquina. Em produção, use HTTPS para o endpoint S3 sempre que ele atravessar uma rede não confiável.

## Subir e verificar

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml up -d --build
docker compose --env-file .env.media-node -f docker-compose.media-node.yml ps
docker compose --env-file .env.media-node -f docker-compose.media-node.yml logs -f mediamtx media-webhook media-worker
```

Valide de dentro do worker os dois destinos necessários:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan about
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan tinker --execute 'dump(Illuminate\Support\Facades\Storage::disk("minio")->exists(".healthcheck"));'
curl -I https://<dominio-do-servidor-principal>/up
```

O retorno `false` para `.healthcheck` é normal; a chamada confirma que bucket, credenciais e endpoint respondem sem lançar exceção.

Do servidor principal, confirme a API privada do MediaMTX:

```bash
curl http://<ip-do-no-de-midia>:9997/v3/config/global/get
```

## Teste funcional

1. Crie uma transmissão no painel principal e copie URL e token de publicação.
2. No OBS, configure a URL RTMP e deixe a chave vazia, pois o token já faz parte da URL.
3. Inicie a transmissão e confirme o estado `live` no painel.
4. Abra a página pública e valide vídeo e comentários.
5. Para um teste rápido de gravação, reduza temporariamente `MEDIAMTX_RECORD_SEGMENT_DURATION` no servidor principal e crie uma nova transmissão; restaure o valor desejado depois.
6. Aguarde o fechamento do segmento e acompanhe os logs de `media-webhook` e `media-worker`.
7. Confirme o objeto no bucket e a nova mídia na categoria `Transmissions`.
8. Valide reprodução e download pelo app principal.

## Falhas e recuperação

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:failed
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:retry all
```

O job é idempotente: novas tentativas usam o mesmo caminho calculado a partir do checksum. Se o storage aceitar o objeto, mas o app principal estiver indisponível, a tentativa seguinte apenas sobrescreve o mesmo objeto e repete o registro. O arquivo em `/recordings` permanece até o app responder com sucesso.

Monitore e faça backup dos volumes `media-recordings` e `media-state`. O primeiro retém vídeos pendentes; o segundo contém a fila SQLite.

## Segurança

- mantenha `.env.media-node` fora do Git;
- use uma VPN entre servidor principal, nó de mídia e storage;
- limite `9000/TCP` do MinIO ao app e aos workers autorizados;
- não exponha o console `9001/TCP` do MinIO à internet;
- use o mesmo `MEDIA_WORKER_TOKEN` no core e nos nós, com pelo menos 64 caracteres aleatórios;
- rotacione credenciais do worker e do storage se houver suspeita de vazamento.
