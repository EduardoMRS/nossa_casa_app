# Build, teste e publicação do aplicativo native

Este guia corresponde ao NativePHP Mobile 4 instalado neste diretório. Execute todos os comandos a partir de `native/`. O diretório gerado `nativephp/`, `vendor/`, `node_modules/`, caches, logs e artefatos de build são dinâmicos e não devem ser versionados; os lockfiles `composer.lock` e `package-lock.json` devem ser versionados.

Documentação oficial de referência:

- [Ambiente](https://nativephp.com/docs/mobile/4/getting-started/environment-setup)
- [Comandos](https://nativephp.com/docs/mobile/4/getting-started/commands)
- [Fluxo de publicação](https://nativephp.com/docs/mobile/4/publishing/introduction)
- [Publicação Android](https://nativephp.com/docs/mobile/4/publishing/android)
- [Publicação iOS](https://nativephp.com/docs/mobile/4/publishing/ios)
- [Push notifications](https://nativephp.com/docs/mobile/4/digging-deeper/push-notifications)

## 1. Matriz de ambientes

| Objetivo | Linux | macOS Apple silicon |
| --- | --- | --- |
| Testes PHP/EDGE | Sim | Sim |
| Build Android | Sim | Sim |
| Build iOS | Não | Sim, com Xcode 16+ |
| Publicar na Play Store | Sim | Sim |
| Publicar na App Store/TestFlight | Não | Sim |

Requisitos comuns:

- PHP CLI 8.4.1 ou superior, com extensões exigidas pelo Composer;
- Composer 2;
- Node.js e npm compatíveis com Vite 8;
- `NATIVEPHP_APP_ID=br.org.nossacasa.app` definido antes de `native:install`;
- acesso HTTPS ao servidor Nossa Casa ou à instalação independente que será testada.

Para Android, instale Android Studio, Android SDK, uma JDK compatível com o Gradle instalado e configure `JAVA_HOME` e `ANDROID_HOME`. Para iOS, use um Mac Apple silicon com Xcode 16+, Command Line Tools, Homebrew e CocoaPods. O simulador iOS não recebe push; esse cenário exige aparelho físico e conta Apple Developer.

## 2. Preparação do checkout

```bash
cd native
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate --force
npm run build
```

Durante desenvolvimento, mantenha `NATIVEPHP_APP_VERSION=DEBUG` se quiser que o bundle PHP seja sempre reextraído. Para builds distribuíveis, use uma versão semântica e incremente sempre `NATIVEPHP_APP_VERSION_CODE`.

Antes de qualquer build, execute a validação rápida:

```bash
php artisan test
npm run build
php artisan native:validate
php artisan native:plugin:list
```

## 3. Android para desenvolvimento e homologação

Instale ou regenere o shell Android após a configuração inicial ou mudanças nativas:

```bash
php artisan native:install android
```

Com emulador aberto ou aparelho com depuração USB autorizado:

```bash
php artisan native:run android --watch
```

Use `--vite` somente se a alteração realmente depender do servidor Vite. As telas atuais são PHP/EDGE, então o fluxo normal não precisa dele:

```bash
php artisan native:run android --watch --vite
```

Antes de publicar, valide a variante otimizada em aparelho físico:

```bash
php artisan native:run android --build=release
```

Critérios mínimos de homologação Android:

- discovery manual e por QR Code;
- login, refresh, troca de igreja, logout e troca de servidor;
- portal, posts, eventos, Bíblia, galeria e transmissão;
- retomada após background e reconexão realtime;
- cache offline e bloqueio de operações privadas sem conexão;
- deep links `nossacasa://posts/...`, `nossacasa://events/...` e `nossacasa://live-streams/...`;
- push em foreground, background e app encerrado, quando o plugin e as credenciais estiverem instalados.

## 4. Android para publicação

Na primeira publicação, gere e guarde o keystore fora do Git:

```bash
php artisan native:credentials android
```

O comando grava as variáveis abaixo no `.env` e cria o keystore em `nativephp/credentials/android/`:

```dotenv
ANDROID_KEYSTORE_FILE=/caminho/seguro/upload-keystore.jks
ANDROID_KEYSTORE_PASSWORD=
ANDROID_KEY_ALIAS=
ANDROID_KEY_PASSWORD=
```

Nunca perca o keystore ou suas senhas. Faça backup cifrado e restrinja o acesso.

Fluxo de release:

```bash
php artisan native:release patch
php artisan test
npm run build
php artisan native:run android --build=release
php artisan native:package android --build-type=bundle
```

O AAB assinado fica em `nativephp/android/app/build/outputs/`. Suba primeiro no track de teste interno do Google Play Console. O upload também pode ser automatizado com uma service account restrita:

```bash
php artisan native:package android --build-type=bundle \
  --upload-to-play-store \
  --play-store-track=internal \
  --google-service-key=/caminho/seguro/play-service-account.json
```

Promova de `internal` para `alpha`, `beta` e `production` somente após a homologação. Não use `--skip-prepare` depois de alterações em plugins, manifesto, Gradle ou código nativo.

## 5. iOS para desenvolvimento e homologação

Esta etapa precisa ser executada em macOS Apple silicon; não é possível validar iOS em host Linux ou Windows.

```bash
cd native
php artisan native:install ios
php artisan native:run ios --watch
```

Para selecionar simulador ou aparelho, informe o UDID como segundo argumento ou use o seletor interativo. Para abrir o projeto gerado no Xcode:

```bash
php artisan native:open ios
```

Antes de publicar, teste a variante otimizada em aparelho físico registrado:

```bash
php artisan native:run ios --build=release
```

Repita os critérios funcionais do Android e valide também permissões, safe areas, retorno de links universais, suspensão/retomada, assinatura e entitlement `aps-environment`.

## 6. iOS para TestFlight e App Store

Pré-requisitos:

- app criado no App Store Connect com bundle ID `br.org.nossacasa.app`;
- certificado de distribuição `.p12` e senha;
- provisioning profile de distribuição `.mobileprovision` correspondente ao bundle ID e ao certificado;
- Team ID;
- chave da API do App Store Connect `.p8`, Key ID e Issuer ID.

As credenciais podem ser preparadas interativamente:

```bash
php artisan native:credentials ios
```

Ou fornecidas no `.env` local/secret store da CI:

```dotenv
NATIVEPHP_DEVELOPMENT_TEAM=
IOS_TEAM_ID=
IOS_DISTRIBUTION_CERTIFICATE_PATH=/caminho/seguro/distribution.p12
IOS_DISTRIBUTION_CERTIFICATE_PASSWORD=
IOS_DISTRIBUTION_PROVISIONING_PROFILE_PATH=/caminho/seguro/profile.mobileprovision
APP_STORE_API_KEY_PATH=/caminho/seguro/AuthKey_KEYID.p8
APP_STORE_API_KEY_ID=
APP_STORE_API_ISSUER_ID=
```

Valide perfil e entitlements antes de exportar:

```bash
php artisan native:package ios --export-method=app-store --validate-profile
```

Fluxo de release e upload:

```bash
php artisan native:release patch
php artisan test
npm run build
php artisan native:run ios --build=release
php artisan native:package ios --export-method=app-store --upload-to-app-store
```

Após o upload, aguarde o processamento no App Store Connect, distribua primeiro pelo TestFlight, resolva avisos de privacidade/entitlements e só então envie para revisão. Para Ad Hoc ou enterprise, altere `--export-method` e use o provisioning profile correspondente.

## 7. Push antes da publicação

O backend, o cadastro/remoção de tokens, as preferências, os deep links e o Push Gateway já estão implementados. Porém, o checkout atual não contém o plugin `nativephp/mobile-firebase` nem os arquivos Firebase. Portanto, não considere push em aparelho físico validado até concluir esta integração.

Para habilitar o transporte do aplicativo:

1. adquirir/instalar e registrar o plugin Firebase compatível com NativePHP Mobile 4;
2. colocar `google-services.json` e `GoogleService-Info.plist` na raiz de `native/`, conforme a documentação do plugin;
3. manter a service account FCM somente no servidor ou no Push Gateway — nunca dentro do app;
4. configurar APNs/FCM server-side sem distribuir chaves às instalações independentes;
5. regenerar o shell com `native:install` e executar os testes em aparelhos físicos;
6. confirmar foreground, background, app encerrado, rotação de token e remoção no logout.

Os arquivos de configuração móveis não devem ser confundidos com a service account do servidor. Revise se eles podem ser versionados conforme a política da organização; credenciais privadas, `.p8`, `.p12`, keystores, senhas e service accounts nunca entram no Git.

## 8. Checklist de publicação

- [ ] `git status` não contém `vendor/`, `node_modules/`, `nativephp/`, Gradle cache, logs ou artefatos de build.
- [ ] Testes PHP/EDGE e build Vite passaram no mesmo commit.
- [ ] Versão e version code foram incrementados.
- [ ] Build `release` foi testado em aparelho físico.
- [ ] Login, multi-tenant, refresh/revogação e offline foram retestados.
- [ ] Push e deep links foram testados nos três estados do app, se push estiver habilitado.
- [ ] APK/AAB/IPA não contém `.env`, tokens, service accounts ou credenciais do backend.
- [ ] Política de privacidade, exclusão de conta, suporte, permissões e dados da loja foram revisados.
- [ ] AAB foi homologado no track interno antes de produção.
- [ ] IPA foi homologado no TestFlight antes da revisão da App Store.
