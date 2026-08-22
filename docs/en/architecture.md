# Architecture and project standards

## Language

English is the canonical source language. Keep identifiers, route names,
configuration keys, comments, and new documentation in English. User-facing
text belongs in the translation catalogs, with English added first.

## Routes

New API resources use plural English nouns under `/api`, for example
`/api/events`, `/api/posts`, `/api/churches`, and `/api/users`. New dashboard
and public URLs also use English, for example `/dashboard/events` and
`/live-streams/{id}`. Legacy Portuguese paths may remain as compatibility
aliases but must not be used in new links or frontend code.

Prefer named routes and Wayfinder-generated helpers. Do not build URLs by
copying a route from an old document.

## Laravel and Vue

- Use Inertia pages under `resources/js/pages`.
- Keep Vue components to one root element.
- Use `useI18n().t()` for visible frontend text.
- Use Form Requests and explicit authorization for writes.
- Scope church data through the current church/domain context.
- Keep media-node credentials out of Git and logs.

## Shared application layers

Reuse the existing shared layers before adding a new utility:

- `app/Helpers/helpers.php` contains global framework helpers such as file
  metadata, URL generation, temporary file links, and storage utilities.
- `app/Traits/` contains reusable model/controller behavior. In particular,
  `HasTranslations`, `ManagesChurchCategories`, and `UploadsMedia` should be
  preferred over duplicating those workflows in a controller.
- `app/Services/` contains integrations and application services such as the
  AI provider, backup, branding, and metrics services.
- `app/Support/` contains focused domain support classes such as church
  context, terminology, mail configuration, content embeds, recording paths,
  and temporary S3 URLs.

Keep authorization and church scoping at the application boundary. A new
service should receive typed dependencies and a new support class should have
one clear responsibility.

## Translation workflow

English is the source locale. Frontend text belongs in
`resources/js/locales/en.json` and is accessed through `useI18n().t()`. Backend
text belongs in `lang/en/*.php` and uses Laravel translation helpers.

After adding English keys, synchronize the configured locales with:

```bash
php artisan lang:translate
php artisan lang:translate --dynamic-only
```

The first command updates static catalogs and dynamic content; the second
updates only database content. Keep `APP_LOCALES` aligned with the locales
that the deployment supports.

## Verification

Before opening a pull request, run:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run lint:check
npm run types:check
docker compose exec -T app npm run build
```

When a change adds a route, regenerate Wayfinder and update the relevant
feature test. When a migration changes persisted data, test both fresh and
upgrade paths.
