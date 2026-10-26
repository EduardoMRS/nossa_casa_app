# TODO — Nossa Casa

Backlog de funcionalidades planejadas. Lembretes de padrão do projeto:
identificadores, rotas e documentação técnica em inglês; textos ao usuário
sempre pelo sistema de tradução (`lang/pt`, `lang/en`). Toda alteração deve ter
teste (Pest) e passar pelo Pint antes de concluir.

---

## 1. Mapa de acessos / visitantes (analytics de páginas públicas) — ✅ concluído

Registrar um log de acessos **sem vincular ID de usuário**, guardando IP e
origem geográfica aproximada, para construir um fluxo de acessos e um mapa de
visitantes. Monitora **apenas páginas públicas**.

- [x] Criar migration + model para o log de acesso (`PublicAccessLog`):
      IP (hasheado), origem aproximada (país/região/cidade/lat/lon), rota/página,
      referer, user-agent, locale, `visited_at`. Não armazena `user_id`.
- [x] Middleware (`LogPublicAccess`, terminável) registra acessos somente em
      páginas públicas (ignora área autenticada, dashboard, api, assets,
      well-known, respostas não-200 e não-HTML).
- [x] Resolver origem aproximada a partir do IP (`VisitorGeolocator`, provedor
      configurável via `services.ip_geolocation`, cache por IP, desabilitado por
      padrão, fallback seguro para IP privado/local).
- [x] Privacidade: IP armazenado como HMAC-SHA256 (salt = app.key); nunca em
      texto puro. Config de retenção/toggle em `.env` (LGPD).
- [x] Agregação para o mapa de visitantes (`PublicAccessLogger::visitorMap`,
      por região/país e período).
- [x] Testes de feature (`tests/Feature/PublicAccessLogTest.php`): registra em
      rota pública, não registra em rota privada/POST/JSON, não grava `user_id`,
      hasheia IP e agrega o mapa.

> Nota de ambiente: Pest/Pint não puderam ser executados nesta máquina
> (`vendor/` ausente; `composer` falha por faltar `ext-intl`/`ext-curl`).
> Verificação feita via `php -l` em todos os arquivos. Rodar
> `php artisan test --filter=PublicAccessLog` e `vendor/bin/pint --dirty` em
> ambiente com dependências instaladas.

## 2. Sistema de grupos (células, estudos bíblicos, etc.) — ✅ concluído

Grupos organizados por **categorias** (ex.: célula, estudo bíblico), com
**local** e um ou mais **responsáveis**.

- [x] Migration + model `Group` (categoria, nome, slug, descrição, `is_public`,
      local via `Address` morphOne, vínculo com `Church`).
- [x] Relacionamento de responsáveis (um ou vários) via pivô `group_user`.
- [x] Adicionado `CategoryType::GROUP` e reaproveitado `Category` (morphToMany).
- [x] CRUD administrativo (`Admin\GroupController` + `Admin/Groups.vue`) sob
      `/dashboard/groups` com `role:church_leader|superadmin|system` e checagem
      `ensureCanCreate/ensureCanManage` (escopo por igreja).
- [x] Exibição pública no portal (`GroupPortalController` + `Groups/Index.vue`)
      em `/{locale}/groups`, somente grupos `is_public`.
- [x] `GroupFactory`, `GroupPolicy` e testes de feature
      (`GroupManagementTest`, 6/6) + Pint.

## 3. Tesouraria (dízimos e ofertas) — ✅ concluído (gateway externo é fase futura)

Controle de dízimos e ofertas com relatório mensal exportável. Usuários
logados **ou não** podem registrar oferta/dízimo. **Sem pagamento dentro da
aplicação nesta fase.**

Acesso restrito a: **tesoureiro, pastor (church_leader) e system**.

- [x] Papel de tesouraria: adicionado `UserRole::TREASURER`; acesso à gestão =
      `treasurer`/`church_leader`/`superadmin`/`system` (via middleware
      `role:treasurer|church_leader|superadmin|system` e `ensureCanManageTreasury`).
      Terminologia `treasurer`/`finance` adicionada em config + lang.
- [x] Migrations + models: `treasury_settings` (Pix/conta por igreja, campos
      sensíveis `encrypted`) e `contributions` (tipo dízimo/oferta, valor,
      `user_id` nullable para anônimo, data).
- [x] Registro por usuário anônimo ou logado (`/{locale}/give`,
      `TreasuryController@contribute`).
- [x] Tela de configurações restrita (`/dashboard/treasury`,
      `Admin/Treasury.vue`) + página pública `Treasury/Contribute.vue`.
- [x] Relatório mensal + exportação CSV (`TreasuryController@export`).
- [x] Autorização garantindo acesso só a tesoureiro/church_leader/system.
- [ ] (Fase futura) Integração com gateway de pagamento externo.
- [x] Testes de feature (`TreasuryManagementTest`, 7/7: anônimo, logado,
      não-aceitando, permissão, criptografia, relatório/export) + Pint.

## 4. Internacionalização — espanhol + revisão de traduções — ✅ concluído

Suporte a espanhol adicionado, com paridade de chaves garantida entre os
idiomas.

- [x] Criado `lang/es` espelhando `lang/pt`/`lang/en` (24 arquivos).
- [x] Todas as chaves traduzidas para espanhol (backend PHP + `es.json` com
      1581 chaves do frontend).
- [x] Paridade de chaves `pt`/`en`/`es` conferida (0 divergências de chaves e
      de placeholders, via script e teste).
- [x] `es` registrado como locale suportado: `i18n.ts`, `LocaleSwitcher.vue`;
      `app.ts` deriva de `supportedLocales`. Backend já suportava via
      `APP_LOCALES=en,pt,es`.
- [x] `LocalizationAudit.test.ts` generalizado para checar `pt` e `es` contra
      `en` (chaves + placeholders). `npm run test:frontend`: 80/80.

## 5. Dashboard administrativa contextual — ✅ concluído

Dashboard mostra dados relevantes **por papel do usuário** (contexto church).

- [x] Botões de módulos removidos no contexto church (sidebar já cobre); mantidos
      só no dashboard de plataforma.
- [x] Últimas atividades (posts/eventos/mídias recentes da igreja).
- [x] Mapa de acessos (consome o módulo 1, `PublicAccessLogger::visitorMap`;
      visível só a church_leader/superadmin/system).
- [x] Conteúdos mais curtidos (posts/mídias por nº de reações, escopo da igreja).
- [x] Atalhos rápidos (criar publicação/evento, galeria) variando por papel.
- [x] Últimos pedidos de oração (visível a leader+; marca anônimos).
- [x] Widget de tesouraria (totais do mês dízimo/oferta; visível a
      treasurer/church_leader/superadmin/system).
- [x] Widgets variam conforme o papel (`DashboardInsights` com gates por papel).
- [x] Testes de feature (`DashboardInsightsTest`, 6/6) validando dados e
      visibilidade por papel + Pint.

> Implementado via `App\Services\DashboardInsights` (injeta `PublicAccessLogger`),
> exposto no render de `/dashboard` como prop `insights` (null no dashboard de
> plataforma) e renderizado em `Dashboard.vue`.

## 6. Embed de mapa da igreja gerado a partir do endereço — ✅ concluído

- [x] Ao salvar/atualizar o endereço, gera automaticamente um `map_embed` OSM
      vinculado às coordenadas (`buildOpenStreetMapEmbed`), persistido no setting.
- [x] Se o `map_embed` for de outro serviço (ex.: Google Maps), **não é
      sobrescrito** na atualização (`isOpenStreetMapEmbed` decide; embed submetido
      não-OSM e embed existente não-OSM são preservados).
- [x] Embed exibido na home pública (já havia iframe; agora persistido).
- [x] Botão "Como chegar" na home (`directionsUrl`) abrindo rota em apps
      externos (Google Maps dir API a partir de coordenadas ou endereço).
- [x] Teste `ChurchMapEmbedTest` (3/3) dos helpers + Pint.
