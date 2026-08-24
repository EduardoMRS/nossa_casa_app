# Desenvolvimento e implantação

## Requisitos

O fluxo de desenvolvimento e produção usa Docker Compose. O mesmo repositório pode ser usado no Windows, Linux e macOS com Docker Desktop ou Docker Engine com Compose v2.

- Docker Desktop (Windows/macOS) ou Docker Engine com plugin Compose (Linux);
- Git;
- pelo menos 4 GB de memória para aplicação, banco, storage, MediaMTX e worker;
- portas 80, 1935, 5173, 8080, 8189/udp, 8888, 9000, 9001 e 9997 conforme os serviços usados.

No Windows, prefira WSL2 quando o file watching estiver lento. No macOS, autorize o Docker Desktop a acessar a pasta do projeto. No Linux, adicione o usuário ao grupo `docker` ou use `sudo`.

## Ambiente de desenvolvimento

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app npm run build
```

Use `docker compose logs -f app db media-worker mediamtx` para acompanhar a inicialização. Para usar o Vite:

```bash
docker compose exec app npm run dev -- --host 0.0.0.0
```

Dentro do container, `DB_HOST` deve ser `db`, nunca `127.0.0.1`.

## Ambiente de produção

Crie um `.env` próprio, fora do Git, e defina pelo menos:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
APP_KEY=base64:substitua-por-uma-chave-aleatoria
DOCKER_DB_PASSWORD=substitua-por-uma-senha-forte
MINIO_ROOT_USER=substitua-por-um-usuario-dedicado
MINIO_ROOT_PASSWORD=substitua-por-uma-senha-forte
```

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app npm run build
```

Coloque HTTPS e firewall diante do webserver. Não exponha a API do MediaMTX (`9997`) nem o console do MinIO (`9001`) à internet.

## Paridade entre ambientes

Desenvolvimento e produção usam os mesmos nomes de serviços e topologia. Devem mudar apenas segredos, URLs públicas, debug, endpoints de storage e regras de firewall. Um media-node separado é configurado conforme [Configuração](configuration.md).
