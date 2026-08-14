# Implantação do servidor principal e storage

Este guia cobre o app Nossa Casa, o banco e o storage definitivo das gravações. Para instalar a máquina que executa o MediaMTX, consulte [Nó de mídia](media-node-deployment.md).

## Responsabilidades do servidor principal

O servidor principal mantém usuários, igrejas, transmissões, autorização, painel e metadados das gravações. Ele controla o MediaMTX pela API privada, mas não recebe o arquivo de vídeo do nó remoto.

```text
painel/app ──API privada──> MediaMTX
     │
     ├── recebe eventos online/offline
     ├── recebe somente metadados da gravação
     └── lê o arquivo já salvo ──S3──> storage compartilhado
                                      └── MinIO no ambiente dev
```

O app e todos os nós de mídia devem apontar `MEDIA_ARCHIVE_DISK` para o mesmo bucket. O nome lógico do disco também deve ser o mesmo em todos os ambientes envolvidos no fluxo.

## Configuração do app

No `.env` do servidor principal, substitua todas as referências entre `<...>`:

```dotenv
MEDIA_NODE_ROLE=core
MEDIA_WORKER_TOKEN=<token-aleatorio-compartilhado-com-os-nos>

MEDIAMTX_API_URL=http://<ip-do-no-de-midia>:9997
MEDIAMTX_API_TOKEN=<token-da-api-do-mediamtx-se-configurado>
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://<dominio-publico-de-transmissao>:1935
MEDIAMTX_PUBLIC_HLS_URL=https://<dominio-publico-de-transmissao>
MEDIAMTX_RECORD_SEGMENT_DURATION=15m

MEDIA_ARCHIVE_DISK=minio
MEDIA_DISK=minio
MINIO_ACCESS_KEY_ID=<usuario-do-minio>
MINIO_SECRET_ACCESS_KEY=<senha-do-minio>
MINIO_DEFAULT_REGION=us-east-1
MINIO_BUCKET=<bucket-compartilhado>
MINIO_ENDPOINT=http://<ip-da-maquina-de-arquivos>:9000
MINIO_URL=https://<dominio-publico-ou-privado-do-storage>/<bucket-compartilhado>
MINIO_TEMPORARY_URL=https://<dominio-alcancavel-pelo-navegador>
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

`MEDIAMTX_RECORD_SEGMENT_DURATION` é aplicado aos paths configurados pelo app. Em desenvolvimento, um valor menor reduz a espera para validar o arquivamento; recrie a transmissão após alterar o valor.

`MINIO_ENDPOINT` é usado pelos contêineres para ler e gravar objetos. `MINIO_TEMPORARY_URL` é a base entregue ao navegador nas URLs temporárias e, portanto, precisa ser alcançável pelo cliente. Eles podem apontar para endereços diferentes.

## Modos de execução com Docker

### Tudo em uma máquina

O `docker-compose.yml` sobe o app, MySQL, MinIO, MediaMTX e o worker de gravações:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose ps
```

Nesse modo, o app acessa a API em `http://mediamtx:9997`, o MediaMTX entrega o webhook ao `webserver` e todos compartilham o volume temporário `mediarecordings`.

### App principal sem transmissão local

Use o override `docker-compose.core.yml` para desativar o MediaMTX e o worker locais:

```bash
docker compose -f docker-compose.yml -f docker-compose.core.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.core.yml exec app php artisan migrate --force
```

Antes de trocar uma instalação já ativa para esse modo, pare os serviços locais:

```bash
docker compose stop mediamtx media-worker
```

Configure no `.env` do core:

```dotenv
MEDIAMTX_API_URL=http://<ip-do-no-de-midia>:9997
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://<dominio-publico-de-transmissao>:1935
MEDIAMTX_PUBLIC_HLS_URL=https://<dominio-publico-de-transmissao>
```

Na máquina separada, use `docker-compose.media-node.yml` conforme o [guia do nó de mídia](media-node-deployment.md). O MinIO pode continuar na máquina principal; basta permitir que o worker remoto alcance `http://<ip-da-maquina-de-arquivos>:9000` pela rede privada.

## Ambiente dev com MinIO

O `docker-compose.yml` principal já inclui `minio` e `minio-init`. O inicializador cria o bucket definido por `MINIO_BUCKET`. Para subir a infraestrutura:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose ps
```

Dentro da rede Docker, o app e o worker usam `http://minio:9000`. No host, a API e o console usam, por padrão:

```text
API S3:  http://localhost:9000
Console: http://localhost:9001
```

Ao testar com um nó de mídia em outra máquina, configure nele:

```dotenv
MINIO_ENDPOINT=http://<ip-da-maquina-que-executa-o-compose-principal>:9000
```

Garanta no firewall que `9000/TCP` aceite o IP privado do nó. Não abra `9001/TCP` publicamente.

## Implantar e validar o core

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list --path=api/internal/media
```

Valide o storage dentro do app:

```bash
docker compose exec app php artisan tinker --execute 'Illuminate\Support\Facades\Storage::disk("minio")->put(".healthcheck", "ok"); dump(Illuminate\Support\Facades\Storage::disk("minio")->get(".healthcheck")); Illuminate\Support\Facades\Storage::disk("minio")->delete(".healthcheck");'
```

Valide a API do nó de mídia a partir do servidor principal:

```bash
curl http://<ip-do-no-de-midia>:9997/v3/config/global/get
```

## Checklist integrado

- o app cria e remove paths pela API privada do MediaMTX;
- o MediaMTX autentica a publicação no endpoint do app;
- eventos `online` e `offline` atualizam o painel;
- o nó grava o objeto diretamente no bucket compartilhado;
- `POST /api/internal/media/recording-stored` transporta apenas JSON, nunca o vídeo;
- o core confirma existência e tamanho antes de publicar a gravação;
- uma repetição do mesmo checksum não cria outra gravação;
- reprodução e download usam uma URL temporária do storage privado.

## Produção

- use HTTPS para HLS e para toda comunicação com o app;
- mantenha API do MediaMTX e endpoint S3 em rede privada/VPN;
- use credenciais S3 dedicadas e limitadas ao bucket/prefixo necessário;
- mantenha versionamento, retenção e backup do bucket conforme a política do projeto;
- monitore filas falhas, espaço local dos nós e disponibilidade do storage;
- não coloque IPs reais nos documentos ou no repositório: use variáveis de ambiente por instalação.
