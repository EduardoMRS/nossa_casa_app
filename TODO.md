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

---

## 7. Customização do portal da church (header, seções da home, footer, CSS e herança) — ✅ concluído

Permitir que a liderança da igreja configure detalhadamente a apresentação do portal público.

- [x] **Menu superior personalizável**: configurar quais links e módulos aparecem na barra superior do portal da igreja (substituindo a lista fixa atual).
- [x] **Seções e layout da Home**:
      - Seleção de blocos visíveis (banners, eventos, notícias/posts, grupos, ministrações/mídias, versículo, mapa).
      - Opção de layout: coluna única ou duas colunas (layout atual).
- [x] **Customização do rodapé (footer)**: personalização de textos, links institucionais e informações de contato adicionais.
- [x] **CSS personalizado**: campo para inserção de CSS customizado sanitizado e injetado no portal da igreja.
- [x] **Herança hierárquica (churches inferiores)**: marcador booleano para definir se as configurações de layout, menu, footer e CSS devem ser propagadas automaticamente para as congregações/filiais subordinadas na rede.
- [x] Testes de feature, permissões e validação de segurança (`tests/Feature/ChurchPortalSettingsTest.php`).

## 8. Definição de Storage próprio da church nas configurações (SFTP, S3, R2, MinIO, B2, Supabase, OneDrive, Padrão) e herança de rede — 📋 planejado

Permitir que a igreja defina um provedor de armazenamento (storage) próprio para salvamento de mídias, transmissões, gravações, livros, anexos e demais conteúdos enviados ou gerados na igreja e em suas filiais.

- [ ] **Drivers suportados**:
      - **Padrão**: fallback para o storage default do sistema / plataforma quando nenhum provedor customizado estiver ativo.
      - **S3 e compatíveis**: AWS S3, Cloudflare R2, MinIO, Supabase Storage, Backblaze B2 (endpoint customizado, bucket, região, key/secret, path style).
      - **SFTP / FTP**: host, porta, usuário, senha ou chave privada, diretório raiz (`root`).
      - **OneDrive**: integração OAuth2 / Graph API para armazenamento de arquivos e mídias.
- [ ] **Interface de configuração e teste de conexão**:
      - Seção no painel de configurações da church (`/dashboard/configuracoes-church`).
      - Formulário reativo com campos específicos por driver selecionado.
      - Ação de teste de conexão com feedback em tempo real (validação de leitura/escrita antes de salvar).
- [ ] **Herança hierárquica na rede**:
      - Marcador booleano `propagate_to_branches` para definir se as filiais/congregações subordinadas devem utilizar o storage da matriz ou manter configuração própria/padrão.
- [ ] **Resolução dinâmica de disco/filesystem**:
      - Service/Factory para instanciar o disco do Flysystem sob demanda com as credenciais resolvidas (próprias ou herdadas da matriz).
      - Roteamento transparente das operações de upload de mídias, gravações do media-worker, biblioteca digital e anexos de formulários.
- [ ] **Testes de integração e feature** (resolução de disco, herança na rede, validação de drivers) + Pint.

## 9. Integração com gateways de pagamento — 📋 planejado

Integrar provedores de pagamento para processamento online de dízimos/ofertas, inscrições em eventos e vendas de produtos.

- [ ] Gateways suportados inicialmente: **Stripe**, **PayPal**, **Asaas** e **PagSeguro**.
- [ ] Armazenamento seguro de credenciais e chaves de API por igreja (chaves criptografadas no banco).
- [ ] Endpoint unificado de webhook para confirmação e conciliação de transações (cartão de crédito, Pix, boleto).
- [ ] Integração com o módulo de Tesouraria para baixa automática de doações processadas por gateway.
- [ ] Testes de integração (mocks de API e webhooks) + Pint.

## 10. Sistema de loja para venda de produtos da church — 📋 planejado

Módulo para comercialização de itens da igreja (livros, camisetas, materiais de estudo, ingressos físicos, etc.).

- [ ] Modelagem de dados: `Product`, imagens, variações, categorias de produto.
- [ ] Preço normal e **preço promocional** (com toggle para ativar/desativar promoção).
- [ ] **Controle de estoque**: quantidade disponível, baixa automática na compra aprovada e devolução em caso de cancelamento.
- [ ] **Limite de compra por documento**: trava opcional configurável por produto limitando o número de unidades adquiridas por CPF/documento.
- [ ] **Reserva temporária de estoque**: retenção de itens no carrinho/checkout durante o fluxo de pagamento com expiração (TTL) para evitar concorrência e venda de itens esgotados.
- [ ] Vitrine pública no portal e checkout integrado aos gateways de pagamento.
- [ ] Painel administrativo de pedidos e gestão de estoque.
- [ ] Testes de feature (estoque concorrente, expiração de reserva, limites por documento) + Pint.

## 11. Sistema de cupons de desconto (loja e eventos) — 📋 planejado

Mecanismo flexível de cupons promocionais ou isenções aplicável a itens da loja e inscrições de eventos.

- [ ] Aplicação unificada: suporte a cupons tanto na compra de produtos da loja quanto em inscrições de eventos (ex.: isentar voluntários/obreiros).
- [ ] Tipos de desconto: percentual (%), valor fixo monetário ou isenção total (100% gratuito).
- [ ] Regras e restrições de uso:
      - Limite máximo de utilizações global ou por usuário.
      - Período de vigência: data de início e término, ou validade indeterminada (ilimitado/sem expiração).
- [ ] Controle de acesso para criação: restrito a usuários do financeiro (`treasurer`), líderes (`leader`), pastores (`church_leader`) e administradores (`system`).
- [ ] Transparência contábil e auditoria:
      - Em casos de 100% de desconto, explicitar no checkout, comprovante e inscrição que o valor foi R$ 0,00 (isenção total).
      - Destacar o percentual e valor nominal reduzido no detalhamento do pedido e nos relatórios de fechamento para não distorcer a contagem contábil.
- [ ] Testes de feature (regras de validação, expiração, aplicação cruzada loja/evento, permissões) + Pint.
