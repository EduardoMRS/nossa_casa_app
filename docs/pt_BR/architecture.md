# Arquitetura e padrões do projeto

## Idioma

Inglês é o idioma fonte. Identificadores, nomes de rotas, chaves de configuração, comentários e documentação nova devem estar em inglês. Textos de usuário pertencem aos catálogos de tradução, começando pelo inglês.

## Rotas

Novos recursos da API usam substantivos plurais em inglês sob `/api`, como `/api/events`, `/api/posts`, `/api/churches` e `/api/users`. URLs públicas e do dashboard também usam inglês, como `/dashboard/events` e `/live-streams/{id}`. Caminhos antigos em português podem existir apenas como aliases de compatibilidade.

Prefira rotas nomeadas e helpers gerados pelo Wayfinder. Não copie URLs de documentos antigos para criar novos links.

## Laravel e Vue

- Use páginas Inertia em `resources/js/pages`.
- Mantenha um único elemento raiz nos componentes Vue.
- Use `useI18n().t()` para todo texto visível no frontend.
- Use Form Requests e autorização explícita para escritas.
- Limite dados da church pelo contexto de domínio/church atual.
- Mantenha credenciais do media-node fora do Git e dos logs.

## Camadas compartilhadas

Reutilize as camadas existentes antes de criar um utilitário:

- `app/Helpers/helpers.php` contém helpers globais para arquivos, URLs, links temporários e storage;
- `app/Traits/` contém comportamentos reutilizáveis, especialmente `HasTranslations`, `ManagesChurchCategories` e `UploadsMedia`;
- `app/Services/` contém integrações e serviços da aplicação, como IA, backup, branding e métricas;
- `app/Support/` contém classes de domínio focadas, como contexto da church, terminologia, email, embeds, gravações e URLs S3 temporárias.

Autorização e escopo da church devem ficar na fronteira da aplicação. Cada service deve receber dependências tipadas e cada support deve ter uma responsabilidade clara.

## Tradução

Inglês é o locale fonte. O frontend usa `resources/js/locales/en.json` através de `useI18n().t()`. O backend usa `lang/en/*.php` com `__()` ou `trans()`.

Depois de adicionar chaves em inglês, sincronize os locales configurados:

```bash
php artisan lang:translate
php artisan lang:translate --dynamic-only
```

O primeiro comando atualiza catálogos estáticos e conteúdo dinâmico; o segundo atualiza somente conteúdo do banco. Mantenha `APP_LOCALES` alinhado aos idiomas suportados pelo deployment.

## Verificação

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run lint:check
npm run types:check
docker compose exec -T app npm run build
```

Ao adicionar uma rota, regenere o Wayfinder e atualize o teste correspondente. Ao alterar uma migration, valide tanto instalação nova quanto upgrade.
