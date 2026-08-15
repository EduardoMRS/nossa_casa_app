# Nossa Casa

Aplicação Laravel com Inertia/Vue, MySQL, MinIO e MediaMTX executada com Docker Compose.

## Requisitos

- Docker Engine com Docker Compose v2;
- CPU `x86-64-v2` ou superior em hosts `amd64`;
- portas disponíveis para HTTP, transmissão e armazenamento;
- arquivo `.env` opcional; se estiver ausente, o container o cria a partir do `.env.example`.

Em uma VM Proxmox, use o tipo de CPU `host` quando todos os nós tiverem processadores compatíveis. Para clusters com CPUs diferentes, use um perfil genérico `x86-64-v2-AES`.

## Configuração local ou produção

Você pode criar o ambiente antes de iniciar:

```bash
cp .env.example .env
```

Se o arquivo `.env` não existir, o entrypoint usa a cópia do `.env.example` embutida na imagem, gera `APP_KEY` automaticamente e continua a inicialização. Para produção, é recomendado fornecer um `.env` próprio com credenciais fortes antes de subir os serviços.

Edite pelo menos `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `DOCKER_DB_PASSWORD`, `MINIO_ROOT_USER` e `MINIO_ROOT_PASSWORD`.

O Compose unificado carrega o `.env` nos containers `app` e `media-worker`. A única diferença esperada entre o ambiente local e o aaPanel é a configuração do ambiente, por exemplo:

```dotenv
APP_ENV=local
```

ou:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

Não copie o `.env` para o repositório. Ele contém chaves e credenciais.

## Inicialização

Suba os serviços em segundo plano:

```bash
docker compose up -d --build
```

Verifique o estado:

```bash
docker compose ps
docker compose logs -f app db minio media-worker
```

Na primeira inicialização, execute as migrações dentro do container da aplicação:

```bash
docker compose exec app php artisan migrate --force
```

Para gerar os assets frontend:

```bash
docker compose exec app npm run build
```

O serviço web usa a porta 80. Em instalações com Nginx ou Apache do aaPanel ocupando essa porta, altere o mapeamento do serviço `webserver` para uma porta interna do host, como `8080:80`, e configure o domínio do aaPanel como proxy reverso para essa porta.

## Banco de dados

O ambiente padrão usa MySQL 8.0.45 no serviço Docker `db`. A conexão entre containers usa `DB_HOST=db`; não use `127.0.0.1` dentro do container da aplicação.

Para preservar dados, não execute `docker compose down -v`. O volume `dbdata` contém o banco MySQL e o volume `miniodata` contém os objetos armazenados no MinIO.

Faça backup antes de trocar a versão do banco ou remover volumes.

## Imagens fixadas

As imagens externas possuem versões explícitas no Compose e nos Dockerfiles:

- PHP `8.5.5-fpm-alpine3.22`;
- Composer `2.8.12`;
- Alpine `3.22.5`;
- Node.js `22.x` via Alpine;
- Nginx `1.29.8-alpine`;
- MySQL `8.0.45`;
- MediaMTX `1.19.3`;
- MinIO `RELEASE.2025-09-07T16-13-09Z`;
- MinIO Client `RELEASE.2025-08-13T08-35-41Z`.

Ao atualizar uma imagem, teste primeiro em um ambiente separado e confirme a compatibilidade dos volumes existentes.

## Diagnóstico rápido

Se aparecer `CPU does not support x86-64-v2`, confira as flags dentro da VM:

```bash
lscpu | grep -E 'Model name|Flags'
```

Em Proxmox, ajuste a CPU da VM no host físico:

```bash
qm set ID_DA_VM --cpu host
```

Se a aplicação reclamar de `.env.example`, confirme que o build foi executado depois da atualização do Dockerfile e que o arquivo `.env.example` existe no contexto de build.
