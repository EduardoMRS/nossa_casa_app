# Build Android e iOS

Este guia cobre preparação, execução local e builds manuais de teste do aplicativo NativePHP. Publicação na Play Store, TestFlight e App Store está documentada em [RELEASE.md](RELEASE.md).

O projeto usa NativePHP Mobile 4.2.0, PHP 8.4+, Node.js 22 e Vite 8. Execute os comandos locais a partir de `native/`, salvo quando o exemplo indicar a raiz do repositório.

## 1. Artefatos e ambientes

| Artefato                    | Linux atual               | Mac Apple silicon         | GitHub Actions |
| --------------------------- | ------------------------- | ------------------------- | -------------- |
| APK Android assinado        | Sim, via `native-builder` | Sim                       | Ubuntu         |
| AAB Android assinado        | Sim, via `native-builder` | Sim                       | Ubuntu         |
| App para simulador iOS      | Não                       | Sim                       | Não publicado  |
| IPA development para iPhone | Não                       | Sim, com assinatura       | macOS 26       |
| IPA App Store               | Não                       | Sim, com conta Apple paga | macOS 26       |

Requisitos Android usados pelo projeto:

- Android SDK 36 e Build Tools 36.0.0;
- JDK 17;
- CMake 3.22.1;
- NDK 27.0.12077973.

O container `native-builder` já contém esse ambiente. Para iOS são necessários Mac Apple silicon, macOS 15.6+, Xcode 26+, Command Line Tools, Homebrew e CocoaPods.

Nunca versione `nativephp/`, `vendor/`, `node_modules/`, caches, APK, AAB, IPA, `.p12`, `.p8`, `.mobileprovision`, keystores ou arquivos `.env`. Mantenha versionados `composer.lock`, `package-lock.json` e `nativephp.lock`.

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

Confirme no `.env`:

```dotenv
NATIVEPHP_APP_ID=br.org.nossacasa.app
NATIVEPHP_APP_VERSION=DEBUG
NATIVEPHP_APP_VERSION_CODE=1
```

Use `DEBUG` durante desenvolvimento. Para artefatos distribuíveis, informe uma versão semântica e um version code superior a todos os builds anteriores.

Validação mínima antes de qualquer build:

```bash
php artisan test
npm run build
php artisan native:validate
php artisan native:plugin:list
```

## 3. Android local

### Executar em emulador ou aparelho

Com o SDK Android instalado, emulador aberto ou aparelho com depuração USB autorizada:

```bash
php artisan native:install android
php artisan native:run android --watch
```

Use `--vite` somente quando precisar do servidor Vite durante o desenvolvimento:

```bash
php artisan native:run android --watch --vite
```

### Criar o keystore de teste

```bash
php artisan native:credentials android
```

O checkout atual usa:

```dotenv
ANDROID_KEYSTORE_FILE=credentials/app-release-key.jks
ANDROID_KEYSTORE_PASSWORD=
ANDROID_KEY_ALIAS=
ANDROID_KEY_PASSWORD=
```

O diretório `credentials/` precisa pertencer ao usuário que executa o comando e deve permanecer ignorado pelo Git. Faça backup cifrado do keystore e das senhas. Para publicação, use uma chave de upload duradoura, conforme [RELEASE.md](RELEASE.md).

### Gerar APK assinado no Linux com Docker

A partir da raiz do repositório:

```bash
docker compose --profile native build native-builder
docker compose --profile native run --rm native-builder \
  native:package android \
  --build-type=release \
  --no-tty \
  --no-interaction
```

Saída padrão:

```text
native/nativephp/android/app/build/outputs/apk/release/app-release.apk
```

Valide a assinatura com o `apksigner` do SDK antes de instalar:

```bash
docker compose --profile native run --rm --no-deps \
  --entrypoint /opt/android-sdk/build-tools/36.0.0/apksigner \
  native-builder verify --verbose \
  /var/www/native/nativephp/android/app/build/outputs/apk/release/app-release.apk
```

O resultado deve começar com `Verifies` e ter ao menos um esquema de assinatura válido.

### Gerar AAB local

```bash
docker compose --profile native run --rm native-builder \
  native:package android \
  --build-type=bundle \
  --no-tty \
  --no-interaction
```

Saída padrão:

```text
native/nativephp/android/app/build/outputs/bundle/release/app-release.aab
```

O APK é usado para instalação direta. O AAB é o artefato destinado à Play Store.

## 4. iOS local

Não é possível compilar iOS no host Linux. Em um Mac compatível:

```bash
cd native
php artisan native:install ios
php artisan native:run ios --watch
```

Para abrir o projeto gerado:

```bash
php artisan native:open ios
```

### Simulador

Selecione um simulador no Xcode ou no seletor do NativePHP. O simulador não comprova assinatura, push notification nem instalação em aparelho físico.

### iPhone próprio com Apple Personal Team

1. Adicione sua Apple Account em **Xcode > Settings > Accounts**.
2. Conecte o iPhone, ative Developer Mode e copie o UDID em **Window > Devices and Simulators**.
3. Abra o projeto com `php artisan native:open ios`.
4. Em **Signing & Capabilities**, escolha seu `Personal Team` e confirme o bundle ID `br.org.nossacasa.app`.
5. Execute uma vez pelo Xcode no aparelho para gerar certificado e provisioning profile.
6. Teste também a variante otimizada:

```bash
php artisan native:run ios --build=release
```

No Personal Team, App ID, aparelhos e profiles expiram em 7 dias. Após o vencimento, gere novamente pelo Xcode, recompile e reinstale o aplicativo. Essa conta não permite TestFlight nem App Store.

## 5. Workflow manual de teste no GitHub

O workflow [.github/workflows/mobile-build.yml](../.github/workflows/mobile-build.yml) usa somente `workflow_dispatch`. Pushes e merges não o executam automaticamente nesta fase.

Ele sempre executa a validação e, conforme os inputs, produz:

- `build_android=true`: APK Android release assinado;
- `build_ios=true`: IPA development assinado para o iPhone registrado.

O workflow precisa estar na branch padrão `main` para aparecer em **Actions > Mobile build**.

### Secrets Android obrigatórios

| Secret                      | Conteúdo                                               |
| --------------------------- | ------------------------------------------------------ |
| `ANDROID_KEYSTORE_BASE64`   | `credentials/app-release-key.jks` codificado em Base64 |
| `ANDROID_KEYSTORE_PASSWORD` | Senha do keystore                                      |
| `ANDROID_KEY_ALIAS`         | Alias existente dentro do keystore                     |
| `ANDROID_KEY_PASSWORD`      | Senha da chave                                         |

No Linux, a partir de `native/`:

```bash
base64 -w 0 credentials/app-release-key.jks | gh secret set ANDROID_KEYSTORE_BASE64
gh secret set ANDROID_KEYSTORE_PASSWORD
gh secret set ANDROID_KEY_ALIAS
gh secret set ANDROID_KEY_PASSWORD
```

No macOS, substitua o primeiro comando por:

```bash
base64 -i credentials/app-release-key.jks | tr -d '\n' | gh secret set ANDROID_KEYSTORE_BASE64
```

Os comandos sem pipe solicitam o valor interativamente. Não informe senhas diretamente na linha de comando nem no histórico do shell.

### Secrets iOS development obrigatórios

| Configuração                            | Conteúdo                                                   |
| --------------------------------------- | ---------------------------------------------------------- |
| `IOS_SIGNING_CERTIFICATE_BASE64`        | Certificado `Apple Development` exportado como `.p12`      |
| `IOS_SIGNING_CERTIFICATE_PASSWORD`      | Senha usada ao exportar o `.p12`                           |
| `IOS_PROVISIONING_PROFILE_BASE64`       | Profile development com o bundle ID e o iPhone registrados |
| `IOS_TEAM_ID`                           | Team ID do Personal Team                                   |
| `IOS_DEVICE_UDID`                       | UDID do mesmo iPhone incluído no profile                   |
| Variable `MOBILE_BUILD_IOS_SIGNED=true` | Habilita o job macOS                                       |

Prepare os arquivos no Mac:

1. Depois de executar o app no iPhone pelo Xcode, abra **Keychain Access > My Certificates**.
2. Exporte o certificado `Apple Development` junto com sua chave privada como `development.p12`.
3. Localize o profile gerado pelo Xcode em `~/Library/Developer/Xcode/UserData/Provisioning Profiles/` ou `~/Library/MobileDevice/Provisioning Profiles/`.
4. Confirme que o profile ainda está válido, usa `br.org.nossacasa.app` e contém o UDID do aparelho.

Configure no GitHub:

```bash
base64 -i development.p12 | tr -d '\n' | gh secret set IOS_SIGNING_CERTIFICATE_BASE64
gh secret set IOS_SIGNING_CERTIFICATE_PASSWORD
base64 -i development.mobileprovision | tr -d '\n' | gh secret set IOS_PROVISIONING_PROFILE_BASE64
gh secret set IOS_TEAM_ID
gh secret set IOS_DEVICE_UDID
gh variable set MOBILE_BUILD_IOS_SIGNED --body true
```

O runner valida antes do build:

- expiração do profile;
- Team ID;
- bundle ID;
- UDID autorizado;
- entitlement de desenvolvimento;
- assinatura final do IPA com `codesign`.

O artifact iOS é retido por 7 dias, mas deixa de ser instalável assim que o profile expira.

### Executar manualmente

Somente Android:

```bash
gh workflow run mobile-build.yml \
  --ref main \
  -f build_android=true \
  -f build_ios=false
```

Somente iOS:

```bash
gh workflow run mobile-build.yml \
  --ref main \
  -f build_android=false \
  -f build_ios=true
```

Android e iOS no mesmo run:

```bash
gh workflow run mobile-build.yml \
  --ref main \
  -f build_android=true \
  -f build_ios=true
```

Acompanhe e baixe os artifacts:

```bash
gh run list --workflow mobile-build.yml --limit 5
gh run watch RUN_ID --exit-status
gh run download RUN_ID --dir artifacts/mobile-build
```

Não ative `build_ios=true` antes de configurar todos os Secrets iOS e renovar o profile vencido.

## 6. Instalação e homologação

### Android

```bash
adb install -r app-release.apk
```

### iOS

Baixe o artifact `nossa-casa-main-ios-*`, conecte o mesmo iPhone ao Mac e instale pelo **Xcode > Window > Devices and Simulators** ou pelo Apple Configurator. O aparelho precisa estar em Developer Mode e o UDID deve constar no profile.

Critérios mínimos para os dois sistemas:

- discovery manual e QR Code;
- login, refresh, troca de igreja, logout e troca de servidor;
- portal, posts, eventos, Bíblia, galeria e transmissão;
- background, retomada e reconexão realtime;
- cache offline e bloqueio de operações privadas sem conexão;
- deep links `nossacasa://posts/...`, `nossacasa://events/...` e `nossacasa://live-streams/...`;
- permissões, safe areas e rotação;
- push nos três estados do app quando o plugin Firebase estiver habilitado.

## 7. Checklist do primeiro run manual

- [ ] `mobile-build.yml` está versionado na `main`.
- [x] O keystore Android local existe e está ignorado pelo Git.
- [ ] Os quatro Secrets Android estão configurados.
- [x] Testes, Vite, `native:validate` e `native:plugin:list` passaram localmente.
- [ ] O primeiro run Android foi executado com `build_ios=false`.
- [ ] O APK do artifact foi instalado e homologado em aparelho físico.
- [ ] Há acesso a um Mac Apple silicon com Xcode 26+.
- [ ] O `.p12`, profile, Team ID e UDID iOS estão configurados e válidos.
- [ ] O IPA foi gerado e instalado no mesmo iPhone registrado.
- [ ] Credenciais e artefatos continuam fora do Git.

## Referências

- [NativePHP: ambiente](https://nativephp.com/docs/mobile/4/getting-started/environment-setup)
- [NativePHP: Android](https://nativephp.com/docs/mobile/4/publishing/android)
- [NativePHP: iOS](https://nativephp.com/docs/mobile/4/publishing/ios)
- [Apple: Personal Team](https://developer.apple.com/help/account/basics/about-your-developer-account)
