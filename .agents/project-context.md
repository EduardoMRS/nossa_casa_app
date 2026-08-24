# AI agent project context

## What this repository is

Nossa Casa is a Laravel 13 application with Inertia/Vue, MySQL, MinIO,
MediaMTX, and a background media worker. The Docker Compose service named
`app` is the Laravel core. The media-node Compose environment is an optional
separate deployment that runs MediaMTX, the webhook, and the worker.

## Where to look first

- `routes/web.php`: public and dashboard Inertia routes.
- `routes/api.php`: JSON endpoints and media-node webhooks.
- `app/Http/Controllers`: request orchestration and authorization boundaries.
- `app/Models`: Eloquent models and church relationships.
- `app/Helpers/helpers.php`: shared global helpers for files, URLs, and storage.
- `app/Traits`: reusable translation, category, and media-upload behavior.
- `app/Services`: integrations and application-level services.
- `app/Support`: focused domain support classes and renderers.
- `resources/js/pages`: Inertia page components.
- `resources/js/components`: reusable Vue components, including the editor,
  phone input, and money input.
- `resources/js/locales/en.json`: canonical frontend translations.
- `docs/README.md`: documentation index.
- `docs/en/`: canonical English operational documentation.
- `docker-compose.yml` and `docker-compose.media-node.yml`: service topology.
- `tools/mcp/server.mjs`: local development MCP for project discovery.
- `app/Console/Commands/ApiTranslateCommand.php`: `lang:translate` workflow
  for static catalogs and dynamic content.

## Important boundaries

Always scope church-owned records using the active domain/church context.
Media files are stored in MinIO or the configured object storage; the core
stores metadata and signed/temporary URLs. MediaMTX API port 9997 is private.
Never commit `.env`, `.env.media-node`, credentials, tokens, or real hostnames.

English is the source language for identifiers and new documentation. Add
English translation keys before other locales. New routes use plural English
resource names; legacy Portuguese URLs exist only for compatibility.

## Safe starting commands

```bash
php artisan route:list --except-vendor
php artisan test --compact --filter=RelevantTest
vendor/bin/pint --dirty --format agent
npm run lint:check
npm run types:check
```

For the complete environment workflow, read `docs/en/development.md` and
`docs/en/configuration.md` before changing Docker or MediaMTX settings. Read
`docs/en/architecture.md` before adding helpers, traits, services, support
classes, or translation keys.
