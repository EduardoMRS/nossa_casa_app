# Nossa Casa documentation

This directory contains the project documentation. English is the canonical
language for source code, configuration keys, route names, agent context, and
new documentation. Portuguese documentation mirrors the English topics and is
kept in sync as a translation; only prose and locale-specific examples differ.

## Available languages

### English

English documentation is stored in [`docs/en/`](en/). Each listed subject has
the equivalent pt-BR document with the same structure and content:

- [Development and deployment](en/development.md): Windows, Linux, macOS,
  Docker Compose, local development, and production deployment.
- [Configuration](en/configuration.md): core environment, MediaMTX,
  media-node, storage, networking, and security settings.
- [Custom components](en/components.md): Markdown editor widgets,
  `PhoneInput`, and `MoneyInput` usage and conventions.
- [Architecture and standards](en/architecture.md): project boundaries,
  naming, localization, testing, and route conventions.
- [Transmissions](en/transmission.md)
- [Core and storage deployment](en/media-core-deployment.md)
- [Media-node deployment](en/media-node-deployment.md)

### Portuguese (pt-BR)

Portuguese documentation is stored in [`docs/pt_BR/`](pt_BR/) with the same
topic structure:

- [Desenvolvimento e implantação](pt_BR/development.md)
- [Configuração](pt_BR/configuration.md)
- [Componentes personalizados](pt_BR/components.md)
- [Arquitetura e padrões](pt_BR/architecture.md)
- [Transmissões](pt_BR/transmission.md)
- [Implantação do core e storage](pt_BR/media-core-deployment.md)
- [Implantação do media-node](pt_BR/media-node-deployment.md)

## Documentation conventions

- Add new technical documentation in English first.
- Keep environment examples free of real secrets.
- Prefer named routes and project commands over copied URLs.
- Link to the canonical English route names and document legacy aliases only
  when migration compatibility matters.
