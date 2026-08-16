# Localization Rules

These rules apply to every backend and frontend change in this project.

## User-facing text

- Never hardcode text that can be shown to a user in PHP controllers, services, requests, jobs, exceptions, Blade templates, or API responses.
- Backend user-facing messages must use Laravel translation keys with `__()`, `trans()`, or the framework's localized validation facilities.
- Do not return raw exception messages to users. Report unexpected exceptions for diagnostics and return a translated, safe message.
- English is the canonical source locale. Add or update the English translation first.
- It is not mandatory to manually create or update every language for every change. Missing locale values can be completed by running `php artisan lang:translate`.
- The translation command uses the locales configured in `APP_LOCALES` (for example, `APP_LOCALES=en,pt,es`). Keep that environment variable updated when a supported language is added.
- Keep translation keys and source-code identifiers in English. The localized values may use the target language.

## Vue frontend

- Never hardcode user-facing text in Vue templates or TypeScript code.
- Use the project's `useI18n().t()` helper for every visible label, description, confirmation, loading state, error, empty state, and notification.
- Add every new frontend translation key to `resources/js/locales/en.json` first. Other locale JSON files may be completed manually or with `php artisan lang:translate`.
- Prefer matching translation namespaces between backend and frontend when a feature has both server and client messages.

## Change language

- Write all code comments, rule updates, implementation notes, and instruction-file changes in English.
- Keep implementation code, translation keys, and technical identifiers in English; put Portuguese or other localized wording only in the appropriate translation catalogs.
- When reviewing a change, search the touched files for quoted user-facing strings before finalizing.
