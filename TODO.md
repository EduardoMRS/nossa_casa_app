# Projeto Nossa Casa APP
**Visão Geral:** Um aplicativo web open source (PWA) voltado para gestão, comunicação e engajamento de igrejas e comunidades de fé. O projeto foi desenhado para ser escalável, moderno e acessível, operando inclusive offline em áreas de baixa conectividade.

---

### 🚀 Mapeamento de Endpoints API & CRUDS Concluídos / Em Expansão

A arquitetura base de CRUDS e rotas da API já foi solidificada usando ULIDs para maior segurança, e controladores padrão já gerenciam as principais regras de negócios.

**1. Relacionamentos Pessoais e Hierarquia:**
*   **Usuários e Perfis:** CRUD base e gestão de papéis (`roles`) finalizados. Perfis (`user_profiles`) estão separados da autenticação para maior segurança.
*   **Hierarquia Institucional:** Endpoints para `communities`, `churches` e `networks` (Matriz/Filial) implementados e mapeados.

**2. Moderação e Mídia (Galeria):**
*   **Upload e Status:** CRUD de Mídias concluído com atributos `mimetype`, `size` e status de visibilidade (`pending`, `approved`, `rejected`). Mídias utilizam relacionamento polimórfico (`mediables`) para serem anexadas a qualquer lugar (Postagens, Eventos).

**3. Categorização, Formulários e Destaques:**
*   **Polimorfismo:** Categorias, Endereços, Reações e Comentários usam arquitetura polimórfica nativa (`categorizables`, `addressable`, `commentable`) para interagir com o resto do sistema.
*   **Formulários:** Construtor de formulários (`forms`) salva `schemas` dinâmicos em JSON, guardando dados do usuário em `form_responses.

**4. Check-in e Presença de Aulas:**
*   **Eventos:** Endpoint de check-in (`/api/events/{id}/checkin`) mapeado e funcionando. 
*   **Classrooms:** Tabela `classroom_presences` pronta para registrar entradas e saídas de alunos (check-in/out dinâmico).

---

### 🧠 Arquitetura de Tradução Automática (IA Engine)

Para manter o projeto universal e localizado dinamicamente, o sistema conta com um motor próprio de inteligência artificial:
- Ao invés de arquivos estáticos pesados, conteúdos dinâmicos gerados no banco (nomes de categorias, títulos, descrições) utilizam a tabela polimórfica `translations`.
- Quando uma tradução não é encontrada, o sistema aciona o `AiModel` (que gerencia custos e os provedores de inteligência artificial, ex: OpenAI, Gemini).
- O model de tradução despacha o `TranslateJob` e a resposta da IA é rastreada no `ai_queries`, preenchendo automaticamente o banco com a nova linguagem sem intervenção humana.

---

### Tecnologias e Detalhes
| Camada | Tecnologias | Objetivo Principal |
| :--- | :--- | :--- |
| **Frontend** | Vue 3, Inertia.js, Vite, i18n | PWA reativa, Localização, renderização controlada pelo backend. |
| **Utilitários (Front)** | VueMask, VueQuill, Axios | Máscaras de input, edição de texto rico (Rich Text), requisições padronizadas. |
| **Backend** | PHP 8.4, Laravel 12 | Lógica de negócios, API, autenticação e roles. |
| **Autenticação** | Breeze / Sanctum, Passkeys, 2FA | Gestão de sessão SPA e tokens, autenticação sem senha (Biometria) e 2FA nativo. |
| **Notificações** | Vapid WebPush | Alertas no navegador e celular (Push Notifications). |

---

### Principais Telas (Front-end Público)

#### Home (`/`)
- Hero banner com os últimos destaques usando o gerenciador de `highlights`.
- Carrossel de imagens disponíveis em media com a coluna 'public' ativa (linha inteira).
- Carrossel de próximos eventos (linha inteira).
- Informações de contato e redes sociais (área central).
- Formulário de pedido de oração.
- Calendário interativo da igreja suportado pela tabela `calendars`.
- Aside com localização em mapa (`Maps API`) na barra lateral.
- Versículo do Dia consumindo a base `libraries` e `vercicles`.

---

### Estrutura do Banco de Dados Atualizada (Schema Laravel)

*Nota: O sistema baseia-se inteiramente em ULIDs como Primary Key para garantir escalabilidade global, alta performance em queries complexas e segurança contra raspagem de dados.*

#### Núcleo de Usuários & Segurança
* **`users`:** `id` (ULID), `first_name`, `last_name`, `email`, `password`, `role` (enum), `birth_date` (date), `two_factor_secret`, `two_factor_recovery_codes`.
* **`passkeys`:** `id`, `user_id` (FK), `name`, `credential_id`, `credential` (json).
* **`user_profiles`:** `id` (ULID), `user_id` (FK), `phone`, `location_lang`, `church_id` (FK), `community_id` (FK), `avatar_path`, `gender`.
* **`user_relationships`:** `id`, `user_id` (FK), `related_user_id` (FK), `relationship_type` (enum).

#### Estrutura Institucional & Multicongregação
* **`addresses` (Polimórfica):** `id` (ULID), `addressable_type`, `addressable_id`, `country`, `state`, `city`, `street`, `number`, `complement`, `neighborhood`, `zipcode`.
* **`communities`:** `id` (ULID), `name`, `slug`, `description`, `logo_path`, `found_date`.
* **`churches`:** `id` (ULID), `name`, `slug`, `community_id` (FK), `status` (enum), `found_date`.
* **`networks`:** `id` (ULID), `parent_church_id` (FK), `child_church_id` (FK), `community_id` (FK).
* **`settings`:** `id` (ULID), `church_id` (FK), `options` (json).

#### Motor de Inteligência Artificial e Internacionalização
* **`ai_models`:** `id`, `provider`, `model_id`, `name`, `status`, `position`, `context_length`, modalidades e custos.
* **`ai_queries`:** `id`, `provider`, `model`, `input`, `response`, `usage` (json - tokens), `status`, `error_message`, `church_id` (FK), `type`.
* **`translations` (Polimórfica):** `id` (ULID), `translatable_type`, `translatable_id`, `translatable_column`, `locale`, `content_original`, `content`.

#### Mídias, Postagens e Interações Sociais
* **`medias`:** `id` (ULID), `uploader_id` (FK), `church_id` (FK), `file_path`, `mimetype`, `size`, `gallery`, `status` (enum).
* **`mediables` (Polimórfica Pivô):** `media_id`, `mediable_id`, `mediable_type`.
* **`posts`:** `id` (ULID), `author_id` (FK), `church_id` (FK), `title`, `slug`, `content`, `published_at`, `expires_at`.
* **`comments` (Polimórfica):** `id` (ULID), `user_id` (FK), `commentable_type`, `commentable_id`, `content`.
* **`reactions` (Polimórfica):** `id` (ULID), `user_id` (FK), `reactionable_type`, `reactionable_id`, `content`, `type`.
* **`categories`:** `id` (ULID), `church_id` (FK), `name`, `slug`, `type`.
* **`categorizables` (Polimórfica Pivô):** `category_id`, `categorizable_id`, `categorizable_type`.

#### Eventos, Aulas e Formulários Dinâmicos
* **`events`:** `id` (ULID), `church_id` (FK), `author_id` (FK), `title`, `slug`, `tags` (json), `description`, `cover_path`, `start_time`, `end_time`.
* **`event_confirmations` (Pivô):** `id` (ULID), `event_id`, `user_id`, `check_in_at`, `check_out_at`.
* **`classrooms`:** `id` (ULID), `church_id` (FK), `name`, `description`, `teacher_id` (FK), limites de idade e membros.
* **`classroom_presences` (Pivô):** `id` (ULID), `classroom_id`, `user_id`, `check_in`, `check_out`.
* **`forms`:** `id` (ULID), `title`, `description`, `church_id` (FK), `schema` (json).
* **`form_responses`:** `id` (ULID), `form_id` (FK), `user_id` (FK), `answers` (json).

#### Conteúdos Auxiliares
* **`libraries` e `vercicles`:** Tabelas para prover conteúdo da bíblia, leitura diária e documentos da congregação.
* **`highlights` (Polimórfica):** `id` (ULID), `highlightable_type`, `highlightable_id`, `church_id`, `order`.
* **`calendars` (Polimórfica):** `id` (ULID), `calendarable_type`, `calendarable_id`, `church_id`, `date`.


### Implementação futura
- [ ] Worker que recebe RTSPs para transmissão ao vivo de video/audio
    - a ideia é simples o banco será compartilhado entre o core da aplicação e o/os workers neles serão recebidos os conteudos para transmissão, no core ao um usuário tentar reproduzir a transmissão a mesma vira do worker
    - ao finalizar a transmissão o conteudo deverá ficar gravado
    - periodicamente o worker rodará um schedule que enviará o coteudo gravado para o core de forma que libere espaço no worker sejá disponibilizado mais rapidamente via o CDN do core
    - core e worker terão a mesma estrutura a difereça será que o worker não recebera acesso direto dos usuários
    - as transmissões em andamento deve aparecer na tela de logs, deve ser possivel derrubar uma conexão por lá
- [ ] templates dinamicos, deve ser possivel mudar o templade visual das seguintes telas e components
    - Wellcome
    - Listagem de postagens publicas
    - Visualização de postagem publica
    - Listagem de evento publico
    - Visualiza de evento publico
    - Visualização de formulario
    - Bliblioteca
    - galeria 