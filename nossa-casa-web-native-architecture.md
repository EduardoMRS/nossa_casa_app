# Nossa Casa — Arquitetura Web e Native com API Compartilhada

## 1. Objetivo

Este documento define a arquitetura recomendada para manter o Nossa Casa funcionando simultaneamente como:

- aplicação web Laravel + Inertia + Vue;
- PWA;
- aplicativo Android e iOS com NativePHP;
- cliente de uma instalação central ou de uma instalação própria de uma igreja/comunidade.

A decisão principal é utilizar `/api/*` como contrato canônico de dados para web e native, sem criar uma árvore separada `/api/mobile/v1`. O site continuará com suas rotas Inertia e autenticação por cookie. O aplicativo utilizará Bearer Token do Laravel Sanctum.

Os objetivos são:

1. compartilhar páginas, componentes, tipos e regras de apresentação;
2. manter regras de negócio e segurança exclusivamente no servidor;
3. não empacotar segredos ou infraestrutura do backend no aplicativo;
4. permitir troca segura do domínio/servidor base;
5. preservar SEO e carregamento inicial das páginas públicas;
6. reduzir duplicação e simplificar manutenção.

---

## 2. Decisões de arquitetura

### 2.1 Contrato único em `/api`

As rotas JSON existentes em `/api` serão ampliadas para atender tanto o site quanto o aplicativo.

- Web autenticada: cookie de sessão + CSRF, reconhecido pelo Sanctum.
- Native: `Authorization: Bearer <sanctum-token>`.
- Seleção da igreja no site: domínio/subdomínio.
- Seleção da igreja no native: header `X-Church-ID`.
- Autorização efetiva: Policies, Gates, roles e membership no servidor.

Não haverá prefixo `/mobile/v1` neste momento. Para permitir evolução futura sem alterar a URL, as respostas devem ser estáveis e mudanças incompatíveis poderão ser negociadas por header:

```http
Accept: application/json
X-Nossa-Casa-API-Version: 1
```

O header de versão é opcional na primeira implementação, mas o servidor deve retornar sua versão no endpoint de descoberta.

#### Ajuste necessário no roteamento atual

Atualmente `routes/api.php` é incluído manualmente no final de `routes/web.php`. Antes de adotar Bearer Token no aplicativo, ele deve ser registrado como arquivo API no `bootstrap/app.php`. O Laravel continuará aplicando automaticamente o prefixo `/api`, mas essas rotas passarão pela pilha `api`, sem exigir CSRF do aplicativo native.

O modo stateful do Sanctum permitirá que os mesmos endpoints reconheçam o cookie seguro do site. Quando não houver cookie, o Sanctum examinará o Bearer Token.

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Demais middlewares atuais...
    })
    ->create();
```

O bloco abaixo deverá ser removido de `routes/web.php` depois que o novo registro estiver testado:

```php
Route::group(['prefix' => 'api'], function () {
    require __DIR__.'/api.php';
});
```

Essa mudança altera a pilha de middleware, não as URLs públicas da API. Testes devem confirmar locale, autenticação, rate limits e respostas de erro antes da remoção definitiva.

### 2.2 Mesmo frontend, adaptadores diferentes

Páginas e componentes Vue não devem conhecer cookie, Bearer Token, Keychain, Service Worker, APNs, FCM ou UnifiedPush diretamente. Eles utilizarão serviços abstratos:

- `HttpClient`;
- `AuthService`;
- `ChurchContextService`;
- `StorageService`;
- `NotificationService`;
- `RealtimeService`;
- `FileService`;
- `NetworkService`.

Cada plataforma terá uma implementação:

```text
Web adapter     Native adapter
Cookie/CSRF     Sanctum Bearer
localStorage    SecureStorage/SQLite
Web Push/VAPID  PushProvider nativo
Laravel Echo    Realtime nativo ou Echo compatível
File input      Camera/Galeria nativa
```

### 2.3 Inertia continua responsável pela navegação web

As rotas web continuam retornando `Inertia::render(...)`. A obtenção de dados seguirá dois padrões:

#### Páginas privadas

Dashboard, perfil, gestão e áreas autenticadas poderão renderizar apenas a página e buscar dados em `/api/*`. Isso faz o web e o native seguirem exatamente o mesmo contrato.

```php
Route::get('/dashboard/events', function () {
    return Inertia::render('Events/Index');
});
```

A página consulta:

```http
GET /api/events
```

#### Páginas públicas e indexáveis

Home pública, posts, eventos, comunidades e demais páginas importantes para SEO devem manter dados iniciais entregues pelo servidor. O controller web e o controller da API utilizarão a mesma Query/Service, sem uma requisição HTTP interna do Laravel para ele mesmo.

```text
PortalQuery
 ├── PortalController web → Inertia initial props
 └── PortalApiController  → JSON Resource
```

Depois da hidratação inicial, atualizações, paginação, comentários e reações utilizam `/api/*`.

### 2.4 Push e tempo real são mecanismos diferentes

- **Tempo real em primeiro plano:** Laravel Reverb/Pusher Protocol para comentários, reações, transmissão e presença.
- **Notificação com o app em segundo plano ou fechado:** APNs direto no iOS; transporte Android configurável, com FCM como opção padrão gratuita e UnifiedPush opcional.
- Eventos WebSocket não são fonte definitiva. Ao reconectar, o cliente deve consultar novamente a API.

### 2.5 Push gratuito e independente de fornecedor

O Nossa Casa não utilizará Firebase como banco, autenticação, storage, analytics, hosting, functions ou backend da aplicação. Toda regra, preferência, fila e auditoria de notificação permanecerá no Laravel.

O envio será exposto por uma interface própria:

```php
interface NativePushProvider
{
    public function send(PushMessage $message, DevicePushToken $device): PushResult;
}
```

Implementações previstas:

```text
ApnsPushProvider         iPhone/iPad
FcmPushProvider          Android com Google Play Services
UnifiedPushProvider      Android compatível, opcional
WebPushProvider          Navegadores/PWA com VAPID
NullPushProvider         Instalações sem push configurado
```

O FCM poderá ser usado apenas como transporte de entrega no Android. O serviço Cloud Messaging é oficialmente sem custo, mas nenhum domínio do projeto deve depender diretamente de suas classes ou payloads.

Evitar completamente o FCM no Android implica aceitar entrega limitada com o app fechado. Android restringe conexões e serviços em background; manter WebSocket/MQTT próprio exigiria serviço persistente, notificação permanente, maior uso de bateria e ainda estaria sujeito às otimizações do fabricante. Por isso:

- FCM será o transporte recomendado para a versão distribuída pela Google Play;
- UnifiedPush poderá atender dispositivos sem Google, quando houver distributor configurado;
- Reverb continuará funcionando apenas como realtime em primeiro plano;
- a ausência de push nunca impedirá o uso do app;
- notificações não críticas também aparecerão na central interna ao reabrir o aplicativo.

#### Credenciais e instalações próprias

APNs e FCM utilizam credenciais vinculadas ao Bundle ID/Application ID do aplicativo oficial. Essas chaves privadas não podem ser distribuídas para servidores independentes de igrejas.

Para o aplicativo único publicado nas lojas, existem duas modalidades:

1. **Instalação central ou domínio personalizado da mesma infraestrutura:** o backend central envia diretamente pelos providers.
2. **Instalação realmente independente:** ela solicita o envio a um Push Gateway oficial, sem receber as credenciais das lojas.

Fluxo recomendado para instalações independentes:

```text
Servidor da igreja
  → solicitação assinada e limitada
  → Push Gateway Nossa Casa
  → APNs ou FCM
  → dispositivo
  → app busca conteúdo completo na API da igreja
```

O gateway deve transportar apenas o mínimo necessário. Preferencialmente a notificação contém texto genérico, `instance_id`, tipo e identificador do evento; o conteúdo privado é obtido diretamente do servidor escolhido pelo usuário depois que o app é aberto.

O gateway pode ser open source e auto-hospedado pelo projeto, mas as credenciais do aplicativo oficial permanecem somente na infraestrutura controlada pelo responsável pela publicação nas lojas.

---

## 3. Fluxo funcional do aplicativo

### 3.1 Primeiro acesso

1. O app inicia com o servidor oficial pré-configurado.
2. O usuário pode continuar, informar outro domínio ou escanear um QR Code.
3. O app normaliza a URL e consulta `/.well-known/nossa-casa.json`.
4. O servidor informa a URL canônica da API, identidade da instalação e recursos disponíveis.
5. O app salva somente metadados públicos da instalação.
6. O portal público é apresentado.
7. O usuário pode navegar publicamente ou fazer login.

Exemplo de descoberta:

```json
{
  "protocol": "nossa-casa",
  "protocol_version": 1,
  "instance_id": "01JABCDEF123456789",
  "instance_name": "Nossa Casa — PIB Ji-Paraná",
  "api_base_url": "https://pib-jipa.org/api",
  "web_base_url": "https://pib-jipa.org",
  "auth_driver": "sanctum",
  "capabilities": [
    "posts",
    "events",
    "bible",
    "live_streams",
    "native_push"
  ],
  "api_version": 1,
  "minimum_app_version": "1.0.0",
  "privacy_url": "https://pib-jipa.org/privacy",
  "terms_url": "https://pib-jipa.org/terms"
}
```

### 3.2 Inicialização com sessão existente

1. Ler servidor/instância atualmente selecionado.
2. Ler credenciais do armazenamento seguro daquele `instance_id`.
3. Se o access token estiver válido, consultar `/api/auth/me`.
4. Se estiver expirado ou próximo da expiração, tentar `/api/auth/refresh`.
5. Obter usuário, memberships, roles e permissões de apresentação.
6. Se houver uma igreja, selecioná-la automaticamente.
7. Se houver várias, abrir a última igreja válida.
8. Se a última igreja não estiver mais disponível, mostrar o seletor.
9. Abrir o portal da igreja selecionada.
10. Conectar ao Reverb e registrar o dispositivo para push, quando autorizado.

### 3.3 Modo público

Sem login, o usuário poderá:

- acessar o portal público;
- pesquisar igrejas/comunidades;
- visualizar posts públicos;
- visualizar eventos públicos;
- acessar biblioteca e Bíblia permitidas;
- assistir transmissões públicas;
- trocar servidor;
- iniciar login ou cadastro, quando permitido.

### 3.4 Seleção de igreja

O app envia o contexto em todas as chamadas relacionadas a uma igreja:

```http
X-Church-ID: 01KCHURCHID
```

O header apenas solicita um contexto. O backend deverá confirmar:

- existência e estado da igreja;
- pertencimento à instalação atual;
- membership do usuário, quando necessário;
- role e permissões;
- escopo público ou privado do recurso.

### 3.5 Troca de igreja

1. Listar memberships disponíveis em `/api/churches` ou `/api/auth/me`.
2. Usuário seleciona uma igreja.
3. Atualizar `selected_church_id` local.
4. Encerrar subscriptions de realtime da igreja anterior.
5. Limpar caches voláteis dependentes de igreja.
6. Buscar dados iniciais da nova igreja.
7. Conectar aos canais autorizados da nova igreja.

A troca de igreja dentro da mesma instalação não exige novo login.

### 3.6 Troca de servidor

1. Cancelar requisições pendentes.
2. Desconectar WebSockets.
3. Consultar e validar o discovery do novo domínio.
4. Comparar `instance_id` e URL canônica.
5. Nunca enviar tokens da instalação anterior ao novo host.
6. Carregar a sessão previamente armazenada para o novo `instance_id`.
7. Se não houver sessão válida, abrir o modo público/login.

Sessões e caches devem ser particionados:

```text
servers/{instance_id}/session
servers/{instance_id}/selected_church
servers/{instance_id}/cache
servers/{instance_id}/devices
```

### 3.7 Funcionamento offline

Quando não houver conexão:

- permitir leitura de dados previamente sincronizados;
- permitir Bíblia offline;
- exibir claramente que os dados podem estar desatualizados;
- impedir ações críticas que precisem de autorização atual;
- opcionalmente enfileirar ações não críticas com idempotency key;
- nunca considerar a simples existência do token como prova de sessão válida.

---

## 4. Autenticação

### 4.1 Web

O site mantém o comportamento atual:

- cookie de sessão HTTP-only;
- proteção CSRF;
- Fortify/passkeys conforme configuração existente;
- `auth:sanctum` reconhecendo requisições stateful;
- contexto da igreja resolvido pelo host.

### 4.2 Native

O aplicativo usa:

- access token do Sanctum enviado como Bearer;
- access token com expiração curta;
- refresh token opaco, aleatório e de uso único;
- tokens armazenados no Keychain/Keystore;
- uma sessão independente por dispositivo e servidor.

Não será utilizado JWT.

### 4.3 Duração recomendada

```text
Access token: 30 a 60 minutos
Refresh token: 30 dias
Sessão inativa: revogável por política do servidor
```

### 4.4 Renovação

```http
POST /api/auth/refresh
Content-Type: application/json

{
  "refresh_token": "random-secret",
  "device_id": "device-ulid"
}
```

Requisitos:

- refresh token salvo apenas como hash no servidor;
- rotação a cada uso;
- token antigo invalidado imediatamente;
- detecção de reutilização;
- reutilização revoga a família da sessão;
- apenas uma renovação simultânea no client;
- requisições que receberam `401` aguardam essa renovação;
- repetir cada requisição no máximo uma vez;
- falha definitiva direciona ao login sem apagar dados offline imediatamente.

### 4.5 Sessões móveis

Tabela sugerida:

```text
mobile_sessions
- id (ULID)
- user_id
- device_id
- device_name
- platform
- app_version
- token_family
- refresh_token_hash
- expires_at
- last_used_at
- last_ip
- user_agent
- revoked_at
- created_at
- updated_at
```

Endpoints:

```text
POST   /api/auth/login
POST   /api/auth/refresh
POST   /api/auth/logout
POST   /api/auth/logout-all
GET    /api/auth/me
GET    /api/auth/sessions
DELETE /api/auth/sessions/{session}
```

---

## 5. Requisitos funcionais obrigatórios

### RF-001 — Descoberta de servidor

O aplicativo deve aceitar o servidor padrão, domínio manual e QR Code, validando a instalação antes do login.

### RF-002 — URL canônica

O discovery deve indicar a API canônica. O client deve utilizar essa URL após validação e não concatenar URLs arbitrariamente.

### RF-003 — Isolamento por instalação

Tokens, igreja selecionada, cache e dispositivos devem ser isolados por `instance_id`.

### RF-004 — Portal público

O app deve permitir navegação pública de acordo com os recursos habilitados pela instalação.

### RF-005 — Login native

O app deve autenticar em `/api/auth/login` e receber access token Sanctum, refresh token e dados mínimos da sessão.

### RF-006 — Restauração de sessão

Ao abrir, o app deve validar ou renovar a sessão antes de liberar recursos privados.

### RF-007 — Seleção automática de igreja

Usuário com uma única membership deve entrar automaticamente na igreja. Com várias, deve abrir a última seleção válida.

### RF-008 — Troca de igreja

O usuário deve trocar de igreja sem novo login quando as igrejas pertencerem à mesma instalação.

### RF-009 — Troca de servidor

O usuário deve poder alternar entre instalações sem que credenciais sejam compartilhadas entre elas.

### RF-010 — API compartilhada

Web e native devem consumir os mesmos endpoints `/api/*`, sujeitos ao mesmo authorization layer.

### RF-011 — Contratos compartilhados e UI por plataforma

O web mantém Inertia/Vue e o aplicativo NativePHP Mobile 4 usa `NativeComponent` + EDGE. As duas interfaces compartilham contratos `/api`, DTOs, regras de cache e comportamento de domínio, mas não compartilham páginas ou layouts de UI. Diferenças de transporte e plataforma ficam em adapters e services.

### RF-012 — SEO público

Páginas públicas indexáveis devem manter dados iniciais renderizados pelo servidor.

### RF-013 — Atualização via API

Paginação, filtros, reações, comentários, inscrições e atualizações posteriores devem utilizar `/api/*`.

### RF-014 — Realtime

Recursos em primeiro plano devem utilizar Reverb/Pusher Protocol com autorização Bearer no native e cookie no web.

### RF-015 — Push nativo

O aplicativo deve registrar o token do provider disponível por dispositivo, permitir revogação/atualização e continuar utilizável quando push não estiver configurado. A aplicação deve suportar APNs direto no iOS, provider Android configurável e Web Push/VAPID na PWA.

### RF-016 — Offline

Bíblia e conteúdos explicitamente sincronizados devem estar disponíveis offline em modo seguro.

### RF-017 — Gestão de dispositivos

O usuário deve visualizar e revogar sessões móveis individualmente ou em conjunto.

### RF-018 — Capacidades do servidor

O client deve respeitar `capabilities` retornadas no discovery para não exibir recursos indisponíveis.

### RF-019 — Compatibilidade

O servidor deve informar versão mínima do app e o app deve informar sua versão em headers.

### RF-020 — Moderação

Conteúdo gerado por usuários deve possuir denúncia, bloqueio, moderação e contato de suporte conforme exigências das lojas.

---

## 6. Requisitos obrigatórios de segurança

### SEC-001 — HTTPS

Produção deve aceitar somente HTTPS com certificado válido. HTTP deve existir apenas em build de desenvolvimento explicitamente habilitado.

### SEC-002 — Normalização de URL

Rejeitar URLs com:

- usuário ou senha embutidos;
- fragmento;
- query string inesperada;
- protocolos diferentes de HTTPS;
- redirecionamentos para protocolos inseguros;
- portas não autorizadas pela política do app, se aplicável.

### SEC-003 — Não compartilhar credenciais entre hosts

Antes de qualquer request autenticada, o host deve ser comparado à URL canônica vinculada à sessão.

### SEC-004 — Armazenamento seguro

Access e refresh tokens devem permanecer no Keychain/Keystore por meio de Secure Storage. Não utilizar `localStorage`, IndexedDB comum ou SQLite sem criptografia para tokens.

### SEC-005 — Expiração e revogação

Access tokens devem expirar. Logout, troca de senha, bloqueio do usuário e revogação administrativa devem invalidar sessões conforme política.

### SEC-006 — Refresh token rotativo

Refresh tokens devem ser aleatórios, opacos, armazenados com hash e rotacionados a cada uso.

### SEC-007 — Detecção de reutilização

Uso de refresh token já consumido deve revogar a família de tokens e gerar evento de segurança.

### SEC-008 — Rate limit

Aplicar limites específicos em:

- discovery abusivo, quando aplicável;
- login;
- recuperação de senha;
- refresh;
- registro de dispositivo;
- comentários, reações e formulários públicos;
- uploads.

### SEC-009 — Policies obrigatórias

Nenhum endpoint deve confiar apenas em role enviada ao frontend ou em `tokenCan`. Toda ação deve passar por Policy/Gate e verificação de membership.

### SEC-010 — Contexto de igreja validado

`X-Church-ID` nunca concede acesso por si só. O servidor resolve e valida o contexto antes do controller.

### SEC-011 — Minimização de dados

API Resources/DTOs devem retornar somente campos necessários. Não serializar Models Eloquent crus.

### SEC-012 — Mass assignment e validação

Todas as mutações devem utilizar Form Requests, DTOs ou comandos validados. Nunca aplicar diretamente o payload recebido ao Model.

### SEC-013 — Uploads

Validar:

- MIME real e extensão;
- tamanho;
- dimensões e duração quando aplicável;
- autorização do usuário;
- associação à igreja;
- nome de arquivo gerado pelo servidor;
- processamento/varredura antes de publicação.

### SEC-014 — URLs de mídia

URLs privadas devem ser temporárias e assinadas pelo servidor. Chaves S3/MinIO nunca devem ser enviadas ao client.

### SEC-015 — Logs seguros

Não registrar:

- Bearer tokens;
- refresh tokens;
- senhas;
- cookies;
- URLs assinadas completas;
- dados sensíveis de crianças;
- payloads privados sem necessidade operacional.

### SEC-016 — Segredos fora do app

O build native não pode conter:

- `.env` do servidor;
- credenciais MySQL;
- chaves S3/MinIO;
- credenciais MediaMTX;
- credenciais privadas de APNs, FCM ou Push Gateway;
- tokens de worker;
- credenciais de e-mail;
- chaves de IA/tradução;
- chaves de assinatura.

### SEC-017 — Aplicação native mínima

O NativePHP deve ser construído a partir de uma aplicação Laravel client mínima. `cleanup_env_keys` e `cleanup_exclude_files` são proteção adicional, não a única barreira.

### SEC-018 — CORS e CSRF

- Web stateful continua protegido por CSRF.
- Native Bearer não depende de CSRF.
- CORS deve listar somente origens web autorizadas.
- Não utilizar `*` com credenciais.

### SEC-019 — Push

Tokens de push devem ser associados a usuário, dispositivo, instalação, plataforma e provider. Logout/revogação deve remover ou desativar a associação. Payloads não devem conter dados sensíveis, especialmente informações de crianças, pedidos de oração privados ou conteúdo administrativo. Credenciais dos providers nunca serão compartilhadas com instalações independentes.

### SEC-020 — Realtime

Canais private/presence devem usar `/broadcasting/auth` protegido por `auth:sanctum`. O servidor deve validar igreja e usuário em cada canal.

### SEC-021 — Dados infantis

Dados do Ministério Kids devem seguir minimização, autorização, retenção e auditoria específicas. Informações de responsáveis e crianças não devem aparecer em payloads genéricos.

### SEC-022 — Moderação e abuso

Comentários, posts, mídia e transmissões devem oferecer denúncia, bloqueio de usuário e ação de moderação auditável.

### SEC-023 — Idempotência

Operações que podem ser repetidas por reconexão devem aceitar `Idempotency-Key`, especialmente inscrições, check-in/check-out, uploads finalizados e ações offline enfileiradas.

### SEC-024 — Atualização obrigatória

Falhas críticas de segurança devem permitir que o servidor exija versão mínima do aplicativo.

---

## 7. Separação recomendada

### 7.1 Servidor

Permanece exclusivamente no servidor:

- Models e banco central;
- Policies, Gates e roles;
- membership e resolução de tenant;
- criação e revogação de tokens;
- assinatura de URLs;
- S3/MinIO;
- MediaMTX, RTMP e processamento de vídeo;
- Reverb backend;
- providers server-side de push e Push Gateway;
- filas, workers e schedulers;
- e-mail;
- logs, métricas e backups;
- tradução/IA com credenciais;
- regras administrativas e auditoria.

### 7.2 Compartilhado entre web e native

- contratos e versões da API;
- formato dos DTOs canônicos;
- regras de cache e expiração;
- convenções de paginação, filtros e erros;
- tipos/interfaces TypeScript reutilizados dentro do frontend web;
- validação de apresentação;
- tradução/i18n;
- comportamento dos stores, implementado no runtime de cada plataforma;
- repositories/interfaces;
- normalização de erros;
- formatação de datas, moedas e mídia;
- regras puramente visuais.

> **Decisão NativePHP Mobile 4:** telas nativas não são WebViews e não executam componentes Vue. Por isso, `resources/js/shared` é a camada compartilhada do web/PWA, enquanto `native/` implementa a mesma experiência e os mesmos contratos em PHP/EDGE. Tentar importar páginas ou layouts Vue no aplicativo violaria o modelo de UI nativa da versão adotada.

### 7.3 Exclusivo web

- bootstrap Inertia web;
- sessão por cookie;
- CSRF;
- Service Worker/PWA;
- Web Push;
- comportamento `beforeinstallprompt`;
- meta tags e SEO;
- adapters do navegador.

### 7.4 Exclusivo native

- bootstrap NativePHP;
- configuração do servidor base;
- discovery da instalação;
- Sanctum Bearer;
- refresh token;
- Secure Storage;
- SQLite/cache offline;
- adapters APNs/FCM/UnifiedPush;
- câmera/galeria;
- deep links;
- adapters nativos;
- lifecycle e conectividade do dispositivo.

---

## 8. Estrutura de diretórios sugerida

Sem mover imediatamente todo o projeto:

```text
nossa_casa_app/
├── app/
│   ├── Actions/
│   ├── Data/
│   ├── Queries/
│   ├── Services/
│   ├── Policies/
│   └── Http/
│       ├── Controllers/
│       │   ├── Web/
│       │   └── Api/
│       ├── Middleware/
│       ├── Requests/
│       └── Resources/
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
│
├── resources/js/
│   ├── app.ts
│   ├── shared/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── layouts/
│   │   ├── composables/
│   │   ├── repositories/
│   │   ├── services/
│   │   ├── stores/
│   │   ├── types/
│   │   └── utils/
│   ├── web/
│   │   ├── adapters/
│   │   └── bootstrap.ts
│   └── native/
│       ├── adapters/
│       └── bootstrap.ts
│
├── native/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── routes/
│   ├── resources/
│   ├── composer.json
│   └── vite.config.ts
│
└── tests/
    ├── Feature/Api/
    ├── Feature/Web/
    └── Security/
```

O diretório `native/` contém um Laravel mínimo. Ele referencia os mesmos contratos HTTP e DTOs canônicos, mas possui UI PHP/EDGE própria e não contém o backend completo.

---

## 9. Sugestões de código

### 9.1 Rotas API

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/portal', [PortalController::class, 'show']);
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:refresh');
});

Route::middleware(['auth:sanctum', 'church.api'])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/posts/{post}/reactions', [PostController::class, 'react']);
    Route::post('/events/{event}/registrations', [EventController::class, 'register']);
});
```

Rotas públicas ainda devem aplicar o contexto da igreja quando necessário. Pode haver um middleware `church.api.optional` para contexto público.

### 9.2 Middleware do contexto API

```php
final class ResolveApiChurch
{
    public function handle(Request $request, Closure $next): Response
    {
        $churchId = $request->header('X-Church-ID');

        abort_if(blank($churchId), 422, 'Church context is required.');

        $church = Church::query()->findOrFail($churchId);

        app(ChurchContext::class)->set($church);

        if ($request->user()) {
            Gate::forUser($request->user())->authorize('access', $church);
        }

        return $next($request);
    }
}
```

O exemplo é propositalmente reduzido. A implementação real deve considerar administradores globais, rotas públicas, instalação e memberships.

### 9.3 Query compartilhada pelo Inertia e API

```php
final class ListChurchPosts
{
    public function handle(Church $church, User $viewer = null): LengthAwarePaginator
    {
        return Post::query()
            ->whereBelongsTo($church)
            ->visibleTo($viewer)
            ->with(['author', 'categories', 'medias'])
            ->latest('published_at')
            ->paginate(15);
    }
}
```

Controller API:

```php
public function index(Request $request, ListChurchPosts $query): AnonymousResourceCollection
{
    $posts = $query->handle(
        app(ChurchContext::class)->church(),
        $request->user(),
    );

    return PostResource::collection($posts);
}
```

Controller web público:

```php
public function index(Request $request, ListChurchPosts $query): Response
{
    $posts = $query->handle(
        app(ChurchContext::class)->church(),
        $request->user(),
    );

    return Inertia::render('Posts/Index', [
        'initialPosts' => PostResource::collection($posts)->resolve($request),
    ]);
}
```

Assim não existe chamada HTTP do controller web para `/api/posts`, mas ambos entregam exatamente o mesmo DTO.

### 9.4 Cliente HTTP compartilhado

```ts
export interface RequestContext {
    baseUrl: string;
    churchId?: string;
    accessToken?: string;
    native: boolean;
}

export const createApiClient = (context: () => RequestContext) => {
    const client = axios.create({
        headers: {
            Accept: 'application/json',
            'X-Nossa-Casa-API-Version': '1',
        },
    });

    client.interceptors.request.use((config) => {
        const current = context();

        config.baseURL = `${current.baseUrl}/api`;
        config.withCredentials = !current.native;

        if (current.churchId) {
            config.headers['X-Church-ID'] = current.churchId;
        }

        if (current.native && current.accessToken) {
            config.headers.Authorization = `Bearer ${current.accessToken}`;
        }

        return config;
    });

    return client;
};
```

### 9.5 Repository utilizado pela página Vue

```ts
export interface PostsRepository {
    index(params?: PostFilters): Promise<Paginated<Post>>;
    show(id: string): Promise<Post>;
    react(id: string, reaction: string): Promise<void>;
}

export const createPostsRepository = (http: AxiosInstance): PostsRepository => ({
    async index(params) {
        const { data } = await http.get('/posts', { params });
        return data;
    },
    async show(id) {
        const { data } = await http.get(`/posts/${id}`);
        return data.data;
    },
    async react(id, reaction) {
        await http.post(`/posts/${id}/reactions`, { reaction });
    },
});
```

### 9.6 Resposta padrão de erro

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["The email field is required."]
  },
  "request_id": "01JREQUESTID"
}
```

O client não deve depender apenas do texto traduzido. O campo `code` deve ser estável.

### 9.7 Headers recomendados

```http
Accept: application/json
X-Nossa-Casa-API-Version: 1
X-Nossa-Casa-App-Version: 1.0.0
X-Nossa-Casa-Platform: android
X-Device-ID: 01JDEVICEID
X-Church-ID: 01JCHURCHID
Authorization: Bearer ...
```

Não utilizar headers de versão/plataforma como autorização.

---

## 10. Contrato de respostas

### Recurso único

```json
{
  "data": {}
}
```

### Coleção paginada

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 38
  }
}
```

### Regras

- datas em ISO 8601;
- IDs como string;
- valores monetários preferencialmente em minor units ou string decimal documentada;
- campos privados ausentes, não preenchidos com dados mascarados sem necessidade;
- nomes dos campos em inglês;
- textos exibidos ao usuário localizáveis;
- enums documentados e estáveis;
- recursos novos adicionados sem remover campos existentes durante uma versão compatível.

---

## 11. TODO de implementação

### Fase 0 — Preparação e testes de regressão

- [x] Criar branch específica para arquitetura web/native.
- [x] Mapear todas as páginas Inertia e respectivos dados iniciais.
- [x] Mapear chamadas Axios atuais e endpoints existentes.
- [x] Classificar páginas em pública indexável, pública dinâmica e privada.
- [x] Criar testes de regressão para login web, domínio de igreja e permissões.
- [x] Definir formato comum de erro da API.
- [x] Definir convenção de API Resources e paginação.

#### Baseline encerrada em 24/08/2026

Este inventário é a referência para a extração progressiva da API. Foram encontrados 47 componentes de página: 44 ativos e 3 sem rota ou fluxo de controller alcançável. Os dados listados são as props de primeiro nível que cada página consome; props globais compartilhadas pelo middleware Inertia não são repetidas.

##### Páginas públicas indexáveis

Estas páginas precisam continuar disponíveis como HTML renderizável e navegável na web, mesmo quando também ganharem endpoints JSON para o aplicativo nativo.

| Componente Inertia | Dados iniciais específicos |
| --- | --- |
| `Portal/Index` | `communities`, `nearbyCommunities`, `locationApplied`, `canOnboard`, `userCommunityId`, `userChurchUrl`, `reviewableRequests`, `myRequests`, `mainDomain` |
| `Home` | `stats`, `featuredEvents`, `latestPosts`, `latestRecordings`, `calendarEvents`, `dailyVerse` |
| `Portal/CommunityShow` | `community`, `stats`, `churches`, `tree`, `locationApplied`, `userChurchUrl` |
| `Events/Index` | `events` |
| `Events/Show` | `event`, `registration` |
| `Posts/PublicIndex` | `posts`, `categories`, `filters`, `mostViewed` |
| `Posts/PublicShow` | `post`, `comments`, `reactions`, `canInteract`, `relatedPosts`, `latestPosts` |
| `Gallery/Index` | `media`, `categories`, `canInteract`, `view` |
| `Library/Index` | `items`, `bible` |
| `Library/Bible` | `versions`, `defaultVersion` |
| `LiveStreams/Show` | `liveStream`, `realtimePrivate`, `comments`, `canComment`, `canModerate` |

##### Páginas públicas dinâmicas

Estas páginas dependem de sessão, token temporário, estado de autenticação ou validação e não são conteúdo indexável.

| Componente Inertia | Dados iniciais específicos |
| --- | --- |
| `Events/Register` | `event`, `form`, `existingAnswers`, `alreadyRegistered` |
| `auth/Login` | `status`, `canResetPassword`, `church` |
| `auth/Register` | `passwordRules` |
| `auth/ForgotPassword` | `status` |
| `auth/ResetPassword` | `token`, `email`, `passwordRules` |
| `auth/VerifyEmail` | `status` |
| `auth/ConfirmPassword` | Nenhuma prop específica |
| `auth/TwoFactorChallenge` | Nenhuma prop específica |
| `ErrorPage` | `status` |

##### Páginas privadas

| Componente Inertia | Dados iniciais específicos |
| --- | --- |
| `Dashboard` | `role`, `kpis`, `modules` |
| `Admin/Branding` | `branding`, `templates`, `terminology`, `terminologyOptions`, `mainDomain` |
| `Admin/Categories` | `title`, `subtitle`, `description`, `types`, `categories` |
| `Admin/ClassroomLabels` | `label` |
| `Admin/Classrooms` | `classrooms`, `members`, `kidsOnly`, `separateKidsMinistry`, `categories` |
| `Admin/Events` | `events`, `categories` |
| `Admin/Form` | `form`, `events`, `posts`, `categories` |
| `Admin/Forms` | `forms`, `categories` |
| `Admin/Highlights` | `highlights`, `churchId`, `candidates` |
| `Admin/LibraryVerse` | `verse`, `libraries`, `categories`, `bible` |
| `Admin/LiveStreams` | `church`, `churches`, `streams`, `canCreate` |
| `Admin/LogsMetrics` | `stats`, `queue`, `logs`, `liveStreams`, `system`, `maintenance` |
| `Admin/MediaModeration` | `title`, `subtitle`, `description`, `stats`, `actions`, `media`, `categories` |
| `Admin/MultiCongregation` | `churches`, `communities`, `networks`, `registrationRequests`, `stats`, `canManageCommunities`, `canChangeChurchCommunity` |
| `Admin/MyPrayers` | `requests`, `stats` |
| `Admin/UserManagement` | `users`, `churches`, `roles`, `stats` |
| `Admin/WallModeration` | `comments`, `stats` |
| `Events/Form` | `event`, `categories`, `forms`, `returnUrl` |
| `Posts/Form` | `post`, `categories`, `forms` |
| `Posts/Index` | `posts`, `categories` |
| `Posts/Show` | `post`, `can`, `comments`, `reactions`, `contentHtml` |
| `settings/Appearance` | Nenhuma prop específica |
| `settings/Security` | `passwordRules`, `canManageTwoFactor`, `canManagePasskeys`, `passkeys`, `twoFactorEnabled`, `requiresConfirmation` |
| `settings/Workspace` | `workspaceUser`, `prayerRequests`, `churchMembers`, `mustVerifyEmail`, `status`, `communities`, `pendingChildCheckouts` |

##### Componentes de página sem uso ativo

| Componente | Situação encontrada |
| --- | --- |
| `Admin/Module` | É renderizado somente por um método privado auxiliar que não possui chamador nem rota ativa. |
| `Welcome` | Não possui `Inertia::render()` ativo; permanece somente na lista de prefetch do bootstrap Vue. |
| `settings/Profile` | Não possui `Inertia::render()` ativo; a rota de perfil atual renderiza `settings/Workspace`. |

##### Chamadas HTTP do frontend atual

As chamadas Axios diretas estão concentradas nos seguintes contratos:

| Domínio/consumidor | Métodos e endpoints |
| --- | --- |
| Comentários e reações (`PostArticle`, galeria e live) | `POST /api/comments`, `PUT /api/comments/{comment}/pin`, `DELETE /api/comments/{comment}`, `POST /api/reactions`, `DELETE /api/reactions/{reaction}` |
| Push/PWA | `POST /api/push-subscriptions` |
| Pedidos de oração (`Home`, `Admin/MyPrayers`, `settings/Workspace`) | `POST /api/prayer-requests` |
| Salas (`Admin/Classrooms`) | `POST /api/classrooms`, `PUT /api/classrooms/{classroom}`, `POST /api/classrooms/{classroom}/check-in`, `POST /api/classrooms/{classroom}/check-out` |
| Formulários (`Admin/Form`, `Events/Register`) | `POST /api/forms`, `PUT /api/forms/{form}`, `POST /api/forms/{form}/responses` |
| Destaques (`Admin/Highlights`) | `PUT /api/church/{church}/highlights` |
| Transmissões (`Admin/LiveStreams`) | `POST /api/live-streams`, `DELETE /api/live-streams/{liveStream}`, `POST /api/live-streams/{liveStream}/rotate-token`, `PUT /api/media/{media}` |
| Igrejas e comunidades (`Admin/MultiCongregation`) | `POST /api/church`, `POST /api/church/{church}` com `_method=PUT`, `DELETE /api/church/{church}`, `POST /api/community`, `PUT /api/community/{community}`, `DELETE /api/community/{community}` |
| Usuários (`Admin/UserManagement`) | `POST /api/user`, `PUT /api/user/{user}`, `DELETE /api/user/{user}` |

Há também chamadas HTTP fora do Axios que precisam ser preservadas durante a migração:

- `fetch` para `/api/bible/*` em `Library/Bible` e `Admin/LibraryVerse`;
- `fetch` para exportação de backup em `Admin/LogsMetrics`;
- mutações pelo router/Form do Inertia para categorias, eventos, posts, mídia, onboarding e configurações;
- chamadas web Axios para onboarding e relacionamentos em `/onboarding/*` e `/settings/*`.

As rotas existentes em `routes/api.php` ainda incluem consultas públicas de comunidades, igrejas, posts, eventos, categorias, mídia e comentários; integrações internas do servidor de mídia; e CRUDs autenticados de formulários, salas, categorias, mídia, transmissões, igrejas, comunidades, usuários, configurações e redes. A Fase 4 deve migrar cada resposta para o contrato abaixo sem alterar autorização, escopo de igreja ou URLs públicas existentes.

##### Contrato comum de erro da API

Toda resposta de erro criada ou migrada para a API nativa deve usar:

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["The email field is required."]
  },
  "request_id": "01JREQUESTID"
}
```

- `message`: resumo localizável para apresentação;
- `code`: identificador estável, em `SCREAMING_SNAKE_CASE`, usado pela lógica do cliente;
- `errors`: mapa opcional de campo para mensagens, presente em falhas de validação;
- `request_id`: identificador opcional de correlação, sem expor stack trace ou segredo;
- status HTTP continua sendo a fonte principal da categoria do erro.

Códigos iniciais reservados: `VALIDATION_ERROR` (422), `UNAUTHENTICATED` (401), `TOKEN_EXPIRED` (401), `FORBIDDEN` (403), `CHURCH_CONTEXT_REQUIRED` (422), `RESOURCE_NOT_FOUND` (404), `RATE_LIMITED` (429), `CONFLICT` (409) e `SERVER_ERROR` (500). Textos podem ser traduzidos; os códigos não podem mudar entre traduções.

##### Convenção de API Resources e paginação

- endpoints novos ou migrados retornam Eloquent API Resources, nunca modelos Eloquent crus;
- recurso único usa `{ "data": {} }` e coleção usa `{ "data": [] }`;
- toda lista potencialmente crescente é paginada no servidor e expõe `links` e `meta` no formato documentado na seção 10;
- filtros e ordenação entram por query string; a resposta mantém a query nos links de paginação;
- `per_page` possui limite máximo definido no backend e o cliente não pode desabilitar a paginação;
- relacionamentos só são incluídos quando carregados explicitamente, evitando consultas N+1 e vazamento de campos;
- nomes, datas, IDs e compatibilidade de campos seguem as regras da seção 10;
- endpoints legados podem ser migrados gradualmente, mas cada endpoint concluído deve ganhar teste de contrato para sucesso, autorização, validação e isolamento por igreja.

### Fase 1 — Sanctum compartilhado

- [x] Instalar/configurar Sanctum no Laravel 13.
- [x] Adicionar `HasApiTokens` ao User.
- [x] Registrar `routes/api.php` no `bootstrap/app.php` usando `api:`.
- [x] Habilitar `$middleware->statefulApi()`.
- [x] Remover o include manual de `routes/api.php` existente em `routes/web.php`.
- [x] Confirmar que as URLs continuam sob `/api/*` sem duplicar o prefixo.
- [x] Confirmar que a pilha API não exige CSRF para Bearer Token.
- [x] Configurar SPA stateful sem quebrar cookies atuais.
- [x] Substituir middleware `auth` das rotas JSON por `auth:sanctum` onde adequado.
- [x] Manter Policies e roles existentes após autenticação.
- [x] Configurar expiração dos access tokens.
- [x] Criar testes autenticando API por cookie.
- [x] Criar testes autenticando API por Bearer.
- [x] Testar que Bearer de um usuário não acessa outra igreja.

### Fase 2 — Sessões móveis e refresh

- [x] Criar migration/model `mobile_sessions`.
- [x] Criar geração segura de `device_id`.
- [x] Implementar `/api/auth/login`.
- [x] Implementar `/api/auth/refresh` com rotação.
- [x] Implementar detecção de reutilização.
- [x] Implementar `/api/auth/logout`.
- [x] Implementar `/api/auth/logout-all`.
- [x] Implementar `/api/auth/sessions`.
- [x] Implementar revogação por dispositivo.
- [x] Revogar sessões conforme bloqueio/troca de senha.
- [x] Aplicar rate limits específicos.
- [x] Criar auditoria sem registrar tokens.

### Fase 3 — Contexto de igreja na API

- [x] Extrair um `ChurchContext` comum.
- [x] Manter resolução web por domínio.
- [x] Criar resolução API por `X-Church-ID`.
- [x] Criar variante opcional para rotas públicas.
- [x] Validar instalação + igreja + membership.
- [x] Adaptar autorização de administradores globais.
- [x] Adicionar `selected_church_id` à resposta `/api/auth/me`.
- [x] Testar usuário com zero, uma e várias memberships.

### Fase 4 — Discovery e servidores próprios

- [x] Implementar `/.well-known/nossa-casa.json`.
- [x] Gerar `instance_id` persistente por instalação.
- [x] Definir `api_base_url` e `web_base_url` canônicos.
- [x] Informar capabilities e versões.
- [x] Criar parser/validador de URL no client.
- [x] Exigir HTTPS em produção.
- [x] Implementar entrada manual de domínio.
- [x] Implementar QR Code de configuração.
- [x] Isolar sessão/cache por `instance_id`.
- [x] Impedir envio de tokens entre hosts.

### Fase 5 — Queries e API Resources compartilhados

- [x] Criar diretórios `Queries`, `Data` e `Resources`.
- [x] Extrair query do portal.
- [x] Extrair query de posts.
- [x] Extrair query de eventos.
- [x] Extrair query de galeria/mídia.
- [x] Extrair query da biblioteca/Bíblia.
- [x] Extrair query de transmissão ao vivo.
- [x] Remover serialização direta de Models.
- [x] Garantir que web e API retornem o mesmo DTO.
- [x] Criar testes de snapshot/estrutura dos Resources.

### Fase 6 — Migração do Inertia para `/api`

- [x] Criar API client compartilhado.
- [x] Criar repositories por domínio.
- [ ] Migrar páginas privadas para carregar dados pela API.
- [x] Manter initial props em páginas públicas indexáveis.
- [x] Hidratar stores com initial props públicas.
- [x] Migrar paginação e filtros públicos para repositories.
- [x] Migrar comentários e reações.
- [x] Migrar formulários e inscrições.
- [x] Padronizar loading, empty state e erros nas páginas migradas.
- [x] Confirmar que PWA continua funcional por teste de manifest, Service Worker e cache por igreja.

> A migração das páginas privadas web permanece incremental e não bloqueia o aplicativo native: o app já consome os endpoints canônicos diretamente. Ela só deve ser marcada após remover a dependência de initial props das telas administrativas que ainda usam Inertia.

### Fase 7 — Separação do frontend

- [x] Criar `resources/js/shared`.
- [x] Mover types e utils usados pelos repositories sem alterar comportamento.
- [x] Registrar componentes reutilizáveis de UI como exclusivos do web; NativePHP 4 exige componentes EDGE.
- [x] Registrar layouts e páginas como implementações específicas de cada plataforma.
- [x] Criar adapters web.
- [x] Criar interfaces de plataforma.
- [x] Remover APIs do navegador da camada compartilhada; usos restantes pertencem somente ao web/PWA.
- [x] Criar testes unitários dos repositories e stores.
- [x] Criar componente-base de modal web acessível e responsivo.
- [x] Substituir confirmações e prompts nativos do navegador por diálogos reutilizáveis e traduzidos.
- [x] Migrar os modais manuais das páginas web para o componente-base compartilhado.

### Fase 8 — Aplicação NativePHP mínima

- [x] Criar Laravel mínimo em `native/`.
- [x] Configurar NativePHP sem segredos do servidor.
- [x] Configurar build Android inicial.
- [x] Consumir os contratos e DTOs canônicos compartilhados, mantendo UI nativa PHP/EDGE.
- [x] Criar bootstrap e adapters native.
- [x] Configurar Secure Storage.
- [x] Configurar SQLite local.
- [x] Implementar tela de servidor/discovery.
- [x] Implementar login e refresh single-flight.
- [x] Implementar seleção automática de igreja.
- [x] Implementar logout e troca de servidor.
- [x] Auditar conteúdo final do APK/AAB.
- [x] Documentar build, homologação e publicação Android/iOS em `native/BUILD.md`.

### Fase 9 — Realtime

- [x] Proteger `/broadcasting/auth` com `auth:sanctum`.
- [x] Autorizar canais por igreja/membership.
- [x] Configurar token Bearer no native.
- [x] Reconectar ao retornar do background.
- [x] Refazer consultas canônicas após reconexão.
- [x] Testar comentários, reações e transmissões.

### Fase 10 — Push nativo

- [x] Criar contrato `NativePushProvider` independente de fornecedor.
- [ ] Implementar `ApnsPushProvider` direto para iOS.
- [ ] Implementar `FcmPushProvider` somente como transporte Android.
- [x] Avaliar/implementar `UnifiedPushProvider` opcional.
- [x] Manter `WebPushProvider` com VAPID para web/PWA.
- [x] Implementar `NullPushProvider` para instalações sem push.
- [x] Criar tabela de dispositivos/push tokens.
- [x] Implementar registro e atualização de token.
- [x] Implementar remoção no logout.
- [x] Criar serviço Laravel de envio independente do provider.
- [x] Criar fila, retry e tratamento de tokens inválidos.
- [x] Definir Push Gateway para instalações realmente independentes.
- [x] Autenticar, autorizar e limitar servidores no gateway.
- [x] Não distribuir credenciais APNs/FCM do app oficial.
- [x] Minimizar payloads que atravessam o gateway.
- [x] Definir categorias/tipos de notificação.
- [x] Criar deep links por tipo.
- [x] Respeitar preferências do usuário.
- [ ] Testar foreground, background e app fechado.
- [x] Testar funcionamento completo com push desabilitado.

> Os adapters server-side APNs/FCM existem, mas a autenticação de produção e o transporte no aparelho não estão concluídos. O app ainda precisa do plugin Firebase do NativePHP, dos arquivos de configuração por plataforma e de testes em dispositivos físicos. As três pendências acima permanecem abertas para não confundir mocks e builds sem credenciais com push end-to-end.

### Fase 11 — Offline

- [x] Definir dados permitidos no cache.
- [x] Implementar Bíblia offline.
- [x] Implementar cache de portal/posts/eventos.
- [x] Criptografar dados locais sensíveis quando necessário.
- [x] Criar política de expiração do cache.
- [x] Exibir indicador offline/desatualizado.
- [x] Avaliar fila offline com idempotency keys.
- [x] Bloquear operações críticas sem validação online.

### Fase 12 — Segurança e lojas

- [ ] Implementar denúncia de conteúdo.
- [ ] Implementar bloqueio de usuário.
- [ ] Publicar contato de suporte.
- [ ] Disponibilizar exclusão de conta no app.
- [ ] Revisar política de privacidade e LGPD.
- [ ] Revisar fluxo do Ministério Kids.
- [ ] Auditar permissões Android/iOS.
- [ ] Auditar logs e telemetria.
- [ ] Fazer teste de autorização multi-tenant.
- [ ] Fazer teste de reutilização de refresh token.
- [ ] Testar instalação maliciosa/domínio inválido.
- [ ] Testar em dispositivos físicos.
- [ ] Preparar conta de revisão das lojas.

---

## 12. Critérios de aceite do MVP

O MVP web/native estará pronto quando:

- [x] o site atual continuar autenticando por cookie;
- [ ] o site consumir `/api` sem regressão de permissões;
- [ ] o app conectar ao servidor oficial e a um servidor próprio;
- [x] tokens de servidores diferentes permanecerem isolados;
- [x] o app renovar sessão sem intervenção do usuário;
- [x] usuário com uma igreja entrar automaticamente nela;
- [x] usuário com várias igrejas puder alternar;
- [x] portal, posts, eventos, Bíblia e transmissão funcionarem;
- [x] páginas públicas continuarem indexáveis;
- [x] realtime funcionar em primeiro plano;
- [ ] push funcionar com o app fechado usando o provider disponível;
- [x] app continuar funcional quando push estiver indisponível/desabilitado;
- [x] instalação independente solicitar push sem receber credenciais do app oficial;
- [x] nenhum segredo do backend existir no pacote native;
- [x] Policies impedirem acesso cross-tenant;
- [x] logout e revogação por dispositivo funcionarem;
- [x] modo offline não permitir ações privadas sem autorização atual;
- [ ] testes web, API e segurança passarem no CI.

---

## 13. Referências técnicas

- [Laravel Sanctum 13.x](https://laravel.com/docs/13.x/sanctum)
- [NativePHP — Authentication](https://nativephp.com/docs/mobile/4/digging-deeper/authentication)
- [NativePHP — Security](https://nativephp.com/docs/mobile/4/digging-deeper/security)
- [NativePHP — Databases and API-first](https://nativephp.com/docs/mobile/4/digging-deeper/databases)
- [NativePHP — WebSockets](https://nativephp.com/docs/mobile/4/digging-deeper/websockets)
- [Firebase Pricing — Cloud Messaging sem custo](https://firebase.google.com/pricing)
- [Android — Background Execution Limits](https://developer.android.com/about/versions/oreo/background)
- [Apple — Envio direto ao APNs](https://developer.apple.com/documentation/usernotifications/sending-notification-requests-to-apns)
