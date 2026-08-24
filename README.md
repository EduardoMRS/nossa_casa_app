# Nossa Casa

O Nossa Casa é uma plataforma open source para igrejas e comunidades de fé.
Ela reúne site, comunicação, eventos, formulários, biblioteca bíblica,
transmissões ao vivo e ferramentas administrativas em um único ambiente.

Repositório: <https://github.com/EduardoMRS/nossa_casa_app>

## Por que este projeto existe

O projeto nasceu de uma ideia que Deus colocou em meu coração depois de uma
visita a uma célula. Naquele momento surgiu a visão da estrutura completa de
uma aplicação com seus módulos e funcionalidades — não para atender apenas
uma igreja específica, mas para criar uma ferramenta que pudesse ajudar até a
igreja mais humilde a ter seu próprio site, publicar conteúdos e organizar
eventos.

Desde o início, uma das prioridades foi ajudar a cuidar das crianças com mais
segurança. Por isso o projeto inclui um fluxo de check-in e check-out para
salinhas, permitindo que os pais tenham mais tranquilidade, inclusive quando
visitam uma igreja pela primeira vez e ainda não conhecem a equipe.

Parti de um levantamento de requisitos funcionais e não funcionais, desenhei
a estrutura inicial do banco de dados e criei mocks das principais telas com
cenários para diferentes níveis de usuário. Com o apoio de um agente de IA,
estruturei a primeira versão de testes e continuo desenvolvendo, testando e
melhorando a plataforma para disponibilizá-la gratuitamente à comunidade,
sem intenção de obter lucro com o projeto.

## O que a plataforma oferece

- páginas públicas para igrejas, comunidades e congregações;
- postagens, categorias, destaques, galeria e mídias privadas;
- criação de eventos, inscrições, confirmações e exportação de dados;
- formulários dinâmicos para inscrições e coleta de informações;
- gestão de usuários, permissões, igrejas filiais e responsáveis;
- check-in e check-out seguro para salinhas e aulas infantis;
- comentários, reações, pedidos de oração e notificações;
- biblioteca bíblica, leituras e conteúdos que podem funcionar offline;
- instalação como PWA em computadores, tablets e celulares;
- transmissão ao vivo via RTMP, sem depender de uma conta no YouTube;
- comentários durante a transmissão e reprodução após o encerramento;
- armazenamento de mídias com URLs temporárias e controle de acesso;
- suporte a MediaMTX, MinIO, worker de mídia e nós de transmissão separados.

## Bíblia e conteúdo offline

A aplicação possui um catálogo de Bíblias com licenças de uso gratuito e os
direitos preservados de seus autores. A PWA permite instalar o Nossa Casa nos
dispositivos e utilizar algumas ferramentas sem conexão, incluindo a própria
biblioteca bíblica quando os dados já tiverem sido armazenados no dispositivo.

## Transmissões ao vivo

O Nossa Casa recebe transmissões diretamente por RTMP. A igreja pode publicar
sem criar uma conta em plataformas externas, como o YouTube. O MediaMTX
distribui o vídeo para o player da aplicação, enquanto os espectadores podem
acompanhar comentários em tempo real. Depois que a transmissão termina, a
gravação pode continuar sendo processada e ficar disponível para reprodução e
publicação na galeria.

## Hospedagem comunitária

No futuro, pretendo disponibilizar um domínio principal para que comunidades
sem condições de manter servidor ou domínio próprios possam utilizar a
plataforma gratuitamente. Inicialmente haverá uma limitação de espaço em disco
para preservar a sustentabilidade do serviço; conforme o projeto crescer,
essa limitação poderá ser revisada.

## Tecnologia

- Laravel 13 e PHP 8.5;
- Vue 3, Inertia.js, TypeScript e Vite;
- MySQL;
- MinIO ou outro storage compatível com S3;
- MediaMTX para ingestão RTMP, HLS e gravações;
- Docker Compose para desenvolvimento e produção;
- PWA, internacionalização e tradução de conteúdo com apoio de IA.

## Como executar

Requisitos: Docker Engine ou Docker Desktop com Compose v2 e Git.

```bash
git clone https://github.com/EduardoMRS/nossa_casa_app.git
cd nossa_casa_app
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

Verifique os serviços com `docker compose ps` e os logs com
`docker compose logs -f app db minio media-worker`. Para produção, use um
arquivo `.env` próprio, credenciais fortes, HTTPS e backups. Nunca publique
arquivos `.env`, tokens ou credenciais no repositório.

## Documentação

A documentação detalhada está em [docs/README.md](docs/README.md), com versões
em inglês e português. Ela cobre desenvolvimento em Windows, Linux e macOS,
configuração do core e do media-node, componentes personalizados, rotas,
tradução, arquitetura e operação das transmissões.

## Contribuição

Sugestões, correções, testes e novas funcionalidades são bem-vindos. Antes de
abrir uma alteração, consulte as orientações em `docs/` e mantenha o padrão de
idioma do projeto: identificadores, rotas e nova documentação técnica usam
inglês; textos apresentados ao usuário devem passar pelo sistema de tradução.

## Propósito

O Nossa Casa é desenvolvido para servir. A intenção é oferecer uma base segura,
acessível e gratuita para igrejas e comunidades, respeitando as licenças dos
conteúdos utilizados e mantendo os dados de cada comunidade sob seu controle.
