# Nó de mídia em outra máquina

Esta configuração separa o recebimento e a gravação das transmissões do servidor principal, mantendo no servidor principal o banco de dados, o painel administrativo, a autorização e o arquivo definitivo das gravações.

## Arquitetura

```text
OBS/câmera ──RTMP/RTSP──> máquina de mídia / MediaMTX ──HLS──> espectadores
                              │
                              ├── autenticação + online/offline ──HTTPS──> app principal
                              │
                              └── segmento local
                                     │
                                  media-relay
                                     │ fila SQLite
                                  media-worker
                                     │ arquivo + checksum + token / HTTPS
                                     └──────────────────────────> app principal
                                                                    │
                                                                    ├── storage definitivo
                                                                    └── mídia “Transmissions”
```

Na máquina remota são executados:

- `mediamtx`: recebe, distribui e grava a transmissão;
- `media-relay`: recebe do MediaMTX apenas a notificação do segmento local;
- `media-worker`: envia o arquivo ao app principal e tenta novamente em caso de falha;
- `media-init`: prepara o SQLite da fila e termina.

Não é necessário executar `media-scheduler` na máquina remota. O worker usa a própria fila, com até 12 tentativas e espera progressiva. O arquivo remoto só é apagado depois que o app principal confirma que o armazenou.

## Pré-requisitos

- Docker Engine com Docker Compose nas duas máquinas;
- HTTPS válido no app principal;
- uma rede privada entre as máquinas, preferencialmente Tailscale ou WireGuard;
- espaço em disco remoto suficiente para reter gravações durante uma indisponibilidade do servidor principal;
- portas públicas `1935/TCP` para publicação RTMP e `8888/TCP` para HLS, conforme o uso;
- porta `9997/TCP` acessível somente pelo IP privado/VPN do app principal.

RTSP (`8554/TCP`) e WebRTC (`8889/TCP` e `8189/UDP`) só precisam ser liberados se forem usados. Não exponha a API `9997` diretamente à internet.

## 1. Configurar o servidor principal

Use o mesmo segredo longo nos dois servidores. No `.env` do app principal:

```dotenv
MEDIA_NODE_ROLE=core
MEDIA_WORKER_TOKEN=COLE_UM_TOKEN_ALEATORIO_DE_64_CARACTERES

# IP da máquina de mídia dentro da VPN
MEDIAMTX_API_URL=http://100.64.0.20:9997

# Endereços entregues ao navegador e ao software de transmissão
MEDIAMTX_PUBLIC_RTMP_URL=rtmp://live.example.com:1935
MEDIAMTX_PUBLIC_HLS_URL=https://live.example.com

# Pode ser um disco local ou um disco S3 configurado no Laravel
MEDIA_ARCHIVE_DISK=recordings
```

Gere o segredo, por exemplo, com `openssl rand -hex 32`. Depois publique a versão atualizada do app, reconstrua a imagem e execute:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
```

O proxy que recebe `POST /api/internal/media/recording-ingest` precisa aceitar o tamanho máximo de uma gravação e um tempo longo de upload. O Nginx incluído no projeto está configurado para 5 GB e 1 hora. Se houver outro proxy, load balancer ou CDN na frente do app, ajuste-o também. Para arquivos grandes, não passe esse endpoint pelo proxy da Cloudflare; use DNS sem proxy, uma URL privada pela VPN ou, futuramente, upload direto para storage S3.

## 2. Preparar a máquina de mídia

Clone a mesma revisão do projeto e entre no diretório:

```bash
git clone SEU_REPOSITORIO nossa-casa-app
cd nossa-casa-app
cp .env.media-node.example .env.media-node
```

Gere uma chave independente para o Laravel local e edite `.env.media-node`:

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

Exemplo dos valores importantes:

```dotenv
APP_KEY=base64:CHAVE_GERADA_ACIMA
MEDIA_WORKER_ID=media-node-manaus-1
MEDIA_WORKER_TOKEN=O_MESMO_TOKEN_CONFIGURADO_NO_APP_PRINCIPAL

MEDIA_CORE_URL=https://app.example.com
MEDIA_CORE_VERIFY_TLS=true

# IP desta máquina dentro da VPN. Use 127.0.0.1 apenas quando o app
# principal estiver na mesma máquina.
MEDIA_API_BIND_IP=100.64.0.20
```

`MEDIA_CORE_URL` deve ser alcançável de dentro dos contêineres da máquina de mídia. Ele é usado para autorização, eventos online/offline e entrega das gravações.

## 3. Subir o nó remoto

```bash
docker compose \
  --env-file .env.media-node \
  -f docker-compose.media-node.yml \
  up -d --build
```

Confira o estado e os logs:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml ps
docker compose --env-file .env.media-node -f docker-compose.media-node.yml logs -f mediamtx media-relay media-worker
```

É normal `media-init` aparecer como encerrado com código `0`: ele é um serviço de inicialização executado uma única vez.

## 4. Rede e DNS

No firewall da máquina de mídia:

- permita `1935/TCP` para os publicadores autorizados;
- permita `8888/TCP` para o proxy/DNS de HLS ou para os espectadores;
- permita `9997/TCP` somente a partir do IP VPN do servidor principal;
- bloqueie o acesso público direto ao `media-relay`, que não publica porta no host.

Um proxy HTTPS pode encaminhar `https://live.example.com` para `http://127.0.0.1:8888`. O endereço configurado em `MEDIAMTX_PUBLIC_HLS_URL` no app principal deve apontar para esse domínio.

Antes de testar uma transmissão, confirme a partir do servidor principal:

```bash
curl http://100.64.0.20:9997/v3/config/global/get
```

E confirme, a partir da máquina de mídia, que o app principal responde:

```bash
curl -I https://app.example.com/up
```

## 5. Testar o fluxo completo

1. Entre no painel administrativo do app principal com usuário `media`, `admin` ou superior.
2. Crie ou abra a transmissão da igreja e copie a URL e o token de publicação.
3. Inicie a transmissão pelo OBS.
4. Confirme no painel que ela ficou online e abra a página pública.
5. Ao completar um segmento, acompanhe `media-worker` nos logs.
6. Confirme que a gravação apareceu nas mídias, na categoria `Transmissions`, e que reprodução e download funcionam.

O envio é idempotente: se a resposta do app principal se perder, o worker pode reenviar o mesmo arquivo sem criar uma segunda gravação. O checksum também impede que um upload corrompido seja aceito.

## Operação e recuperação

Para ver jobs que falharam no nó remoto:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:failed
```

Depois de corrigir rede, certificado, token ou espaço em disco, tente novamente:

```bash
docker compose --env-file .env.media-node -f docker-compose.media-node.yml exec media-worker php artisan queue:retry all
```

Monitore o volume `media-recordings`: em uma falha prolongada ele continuará crescendo, justamente para preservar os arquivos. Faça backup do volume `media-state`, pois ele contém a fila SQLite. Para atualizar o nó:

```bash
git pull
docker compose --env-file .env.media-node -f docker-compose.media-node.yml up -d --build
```

## Segurança

- use HTTPS válido e mantenha `MEDIA_CORE_VERIFY_TLS=true` em produção;
- armazene `.env.media-node` somente na máquina remota e nunca o envie ao Git;
- use uma VPN para a API de controle do MediaMTX;
- rotacione `MEDIA_WORKER_TOKEN` nos dois servidores se houver suspeita de vazamento;
- faça backup do arquivo definitivo no servidor principal ou no storage S3;
- limite no firewall quem pode publicar em `1935`, além do token de transmissão já exigido pelo app.
