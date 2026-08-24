# Release na Play Store e App Store

Este guia cobre a preparação das contas, assinatura, build, upload, homologação e publicação do Nossa Casa. Para builds manuais de teste, consulte [BUILD.md](BUILD.md).

O workflow [.github/workflows/mobile-release.yml](../.github/workflows/mobile-release.yml) é acionado quando uma GitHub Release com tag semântica é publicada. Ele valida o projeto, gera APK e AAB Android, opcionalmente gera IPA App Store e anexa os artefatos à GitHub Release. Uploads para as lojas são controlados por variables separadas.

## 1. Convenções da release

- Bundle/package ID: `br.org.nossacasa.app`.
- Tags aceitas: `vMAJOR.MINOR.PATCH`, por exemplo `v1.0.0`.
- `NATIVEPHP_APP_VERSION` recebe a versão da tag.
- `NATIVEPHP_APP_VERSION_CODE` recebe o número único do GitHub Actions run.
- Nunca reutilize um version code/build number já enviado às lojas.
- Crie primeiro uma GitHub Release como draft, confira tag e changelog e só então publique.

Antes de cada release:

```bash
cd native
php artisan test
npm run build
php artisan native:validate
php artisan native:plugin:list
```

Homologue os builds release em aparelhos físicos antes de publicar:

```bash
php artisan native:run android --build=release
php artisan native:run ios --build=release
```

O segundo comando exige um Mac compatível.

## 2. Secrets e variables do workflow de release

### Android — sempre necessários

| Secret                      | Finalidade                               |
| --------------------------- | ---------------------------------------- |
| `ANDROID_KEYSTORE_BASE64`   | Chave de upload JKS codificada em Base64 |
| `ANDROID_KEYSTORE_PASSWORD` | Senha do keystore                        |
| `ANDROID_KEY_ALIAS`         | Alias da chave                           |
| `ANDROID_KEY_PASSWORD`      | Senha da chave                           |

### Android — upload automático opcional

| Configuração                                     | Finalidade                                          |
| ------------------------------------------------ | --------------------------------------------------- |
| `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON_BASE64` Secret | JSON da service account codificado em Base64        |
| `MOBILE_UPLOAD_ANDROID_TO_PLAY=true` Variable    | Envia o AAB para o track interno durante o workflow |

Com `MOBILE_UPLOAD_ANDROID_TO_PLAY` ausente ou `false`, o workflow apenas gera e anexa APK/AAB à GitHub Release.

### iOS App Store — build opcional

| Configuração                                          | Finalidade                                             |
| ----------------------------------------------------- | ------------------------------------------------------ |
| `IOS_DISTRIBUTION_CERTIFICATE_BASE64` Secret          | Certificado Apple Distribution `.p12` em Base64        |
| `IOS_DISTRIBUTION_CERTIFICATE_PASSWORD` Secret        | Senha do `.p12`                                        |
| `IOS_DISTRIBUTION_PROVISIONING_PROFILE_BASE64` Secret | Profile App Store Connect `.mobileprovision` em Base64 |
| `IOS_TEAM_ID` Secret                                  | Team ID da conta paga                                  |
| `MOBILE_BUILD_IOS_RELEASE_SIGNED=true` Variable       | Habilita o job de IPA App Store                        |

### iOS App Store — upload automático opcional

| Configuração                                   | Finalidade                                          |
| ---------------------------------------------- | --------------------------------------------------- |
| `APP_STORE_API_KEY_BASE64` Secret              | Chave privada App Store Connect `AuthKey_*.p8`      |
| `APP_STORE_API_KEY_ID` Secret                  | Key ID da API Key                                   |
| `APP_STORE_API_ISSUER_ID` Secret               | Issuer ID da API Key                                |
| `MOBILE_UPLOAD_IOS_TO_APP_STORE=true` Variable | Envia o IPA para processamento no App Store Connect |

Com `MOBILE_BUILD_IOS_RELEASE_SIGNED=false`, o job iOS é ignorado. Com o build iOS habilitado e `MOBILE_UPLOAD_IOS_TO_APP_STORE=false`, o workflow gera e anexa o IPA, mas não exige a API Key.

## 3. Preparação para Google Play

### Conta e cadastro do aplicativo

1. Conclua a verificação da conta no Play Console.
2. Crie o aplicativo com package name `br.org.nossacasa.app`.
3. Defina idioma padrão, nome, tipo de aplicativo, preço e e-mail de suporte.
4. Aceite os termos do Play App Signing.
5. Preencha todas as tarefas do Dashboard antes da primeira submissão.

Prepare a ficha da loja:

- nome curto e descrição completa;
- ícone 512 × 512;
- feature graphic 1024 × 500;
- screenshots reais de celular e demais formatos suportados;
- categoria, tags, e-mail, site e política de privacidade;
- classificação indicativa e público-alvo;
- declaração de anúncios;
- seção Data safety coerente com o comportamento real;
- instruções e conta de demonstração para áreas protegidas;
- URL e fluxo de exclusão de conta, quando aplicável;
- declarações de permissões sensíveis, se o app as solicitar.

### Chave de upload e Play App Signing

O arquivo usado por `ANDROID_KEYSTORE_BASE64` é a chave de upload. Faça backup cifrado do JKS, alias e senhas em local separado do repositório e do GitHub. Ative Play App Signing no primeiro envio; a Play Store passa a proteger a chave de assinatura distribuída aos usuários enquanto você mantém a chave de upload.

Confirme antes da primeira release:

```bash
keytool -list -v -keystore credentials/app-release-key.jks
```

Não troque a chave de upload entre builds sem executar o procedimento oficial de redefinição, pois versões assinadas com credenciais incompatíveis não atualizam instalações existentes.

### Service account para upload pelo runner

1. Em um projeto Google Cloud controlado pela organização, habilite a Google Play Developer API.
2. Crie uma service account exclusiva para releases móveis.
3. Gere uma chave JSON e guarde-a fora do repositório.
4. No Play Console, conceda à service account acesso somente ao app Nossa Casa e às permissões necessárias para gerenciar releases nos tracks usados.
5. Faça o primeiro upload e a configuração do Play App Signing pela interface do Play Console antes de depender da automação.

No Linux:

```bash
base64 -w 0 play-service-account.json | gh secret set GOOGLE_PLAY_SERVICE_ACCOUNT_JSON_BASE64
gh variable set MOBILE_UPLOAD_ANDROID_TO_PLAY --body true
```

No macOS:

```bash
base64 -i play-service-account.json | tr -d '\n' | gh secret set GOOGLE_PLAY_SERVICE_ACCOUNT_JSON_BASE64
gh variable set MOBILE_UPLOAD_ANDROID_TO_PLAY --body true
```

O workflow envia inicialmente para `internal`. Não altere diretamente para produção sem homologação e revisão das políticas.

### Build Android local

APK para homologação direta:

```bash
php artisan native:package android --build-type=release
```

AAB para Play Store:

```bash
php artisan native:package android --build-type=bundle
```

Upload local para teste interno:

```bash
php artisan native:package android \
  --build-type=bundle \
  --upload-to-play-store \
  --play-store-track=internal \
  --google-service-key=/caminho/seguro/play-service-account.json
```

### Tracks e promoção

1. Envie primeiro para Internal testing.
2. Instale pela Play Store e valide login, atualização, offline, deep links, permissões e integridade da assinatura.
3. Promova para Closed testing e registre feedback e correções.
4. Use Open testing apenas quando a ficha já puder ficar publicamente visível.
5. Envie para Production somente depois de concluir todas as declarações e critérios da conta.

Contas pessoais criadas após 13 de novembro de 2023 precisam manter pelo menos 12 testadores inscritos continuamente em um teste fechado por 14 dias antes de solicitar acesso à produção. Verifique no Dashboard se essa regra se aplica à conta usada.

## 4. Preparação para Apple App Store

A publicação exige adesão paga ao Apple Developer Program. O Personal Team gratuito serve apenas para desenvolvimento em aparelhos próprios e não fornece App Store Connect, TestFlight nem certificados de distribuição.

### Conta, identificador e App Store Connect

1. Conclua a adesão ao Apple Developer Program e aceite contratos pendentes.
2. Registre um App ID explícito `br.org.nossacasa.app` em Certificates, Identifiers & Profiles.
3. Habilite somente as capabilities realmente usadas pelo aplicativo.
4. Crie o app no App Store Connect antes do primeiro upload.
5. Informe nome, SKU interno, bundle ID e plataforma iOS.

Prepare os dados da loja:

- descrição, subtítulo, palavras-chave, categoria e URL de suporte;
- política de privacidade e respostas de App Privacy;
- screenshots por tamanho exigido e ícone do app;
- faixa etária;
- informações de export compliance/criptografia;
- instruções para App Review e conta de demonstração funcional;
- detalhes de coleta, login, exclusão de conta e permissões;
- disponibilidade, preço e estratégia de liberação manual, automática ou gradual.

### Certificado e provisioning profile

1. Crie um certificado `Apple Distribution` na conta Apple Developer.
2. Instale-o no Keychain Access do Mac e exporte certificado mais chave privada como `distribution.p12`.
3. Crie um profile **App Store Connect** para `br.org.nossacasa.app` usando esse certificado.
4. Baixe como `distribution.mobileprovision`.
5. Guarde `.p12`, senha e profile em cofre seguro e fora do Git.

O workflow valida expiração, Team ID, bundle ID, entitlement de distribuição e assinatura do IPA.

Configure os Secrets no macOS:

```bash
base64 -i distribution.p12 | tr -d '\n' | gh secret set IOS_DISTRIBUTION_CERTIFICATE_BASE64
gh secret set IOS_DISTRIBUTION_CERTIFICATE_PASSWORD
base64 -i distribution.mobileprovision | tr -d '\n' | gh secret set IOS_DISTRIBUTION_PROVISIONING_PROFILE_BASE64
gh secret set IOS_TEAM_ID
gh variable set MOBILE_BUILD_IOS_RELEASE_SIGNED --body true
```

### API Key para upload automático

No App Store Connect, acesse **Users and Access > Integrations > App Store Connect API** e crie uma chave com o menor papel que permita os uploads necessários. Baixe o `.p8` imediatamente e guarde Key ID e Issuer ID.

```bash
base64 -i AuthKey_KEYID.p8 | tr -d '\n' | gh secret set APP_STORE_API_KEY_BASE64
gh secret set APP_STORE_API_KEY_ID
gh secret set APP_STORE_API_ISSUER_ID
gh variable set MOBILE_UPLOAD_IOS_TO_APP_STORE --body true
```

Não reutilize essa chave em serviços que não participam do release e revogue-a se houver suspeita de exposição.

### Build iOS local

Validação isolada do profile:

```bash
php artisan native:package ios \
  --export-method=app-store \
  --validate-profile
```

O comando acima apenas valida; ele não gera o IPA. Execute o empacotamento separadamente:

```bash
php artisan native:package ios \
  --export-method=app-store \
  --certificate-path=/caminho/seguro/distribution.p12 \
  --certificate-password='SENHA' \
  --provisioning-profile-path=/caminho/seguro/distribution.mobileprovision \
  --team-id=TEAM_ID \
  --rebuild
```

Upload local opcional:

```bash
php artisan native:package ios \
  --export-method=app-store \
  --upload-to-app-store \
  --api-key-path=/caminho/seguro/AuthKey_KEYID.p8 \
  --api-key-id=KEY_ID \
  --api-issuer-id=ISSUER_ID \
  --certificate-path=/caminho/seguro/distribution.p12 \
  --certificate-password='SENHA' \
  --provisioning-profile-path=/caminho/seguro/distribution.mobileprovision \
  --team-id=TEAM_ID \
  --rebuild
```

Evite colocar uma senha real diretamente no histórico; prefira as variáveis documentadas em `.env.ci.example` ou um secret store local.

### TestFlight e App Review

1. Aguarde o processamento do build no App Store Connect.
2. Resolva erros, warnings, export compliance e informações ausentes.
3. Adicione o build a um grupo TestFlight interno.
4. Homologue em aparelhos físicos; builds TestFlight podem ser testados por até 90 dias.
5. Para testadores externos, preencha as informações beta e aguarde a revisão beta quando exigida.
6. Crie a versão da App Store, selecione o build aprovado e complete todos os metadados.
7. Adicione à submissão e envie para App Review.
8. Após aprovação, publique conforme a estratégia escolhida e monitore crashes e avaliações.

## 5. Executar o workflow de release

### Primeiro ensaio sem upload nas lojas

Mantenha:

```text
MOBILE_UPLOAD_ANDROID_TO_PLAY=false
MOBILE_UPLOAD_IOS_TO_APP_STORE=false
```

Habilite `MOBILE_BUILD_IOS_RELEASE_SIGNED=true` somente quando as credenciais de distribuição pagas estiverem prontas. Publique uma GitHub Release de versão inicial de homologação:

```bash
gh release create v0.1.0 \
  --draft \
  --title 'v0.1.0' \
  --generate-notes

gh release edit v0.1.0 --draft=false
```

A segunda operação dispara `mobile-release.yml`. Acompanhe:

```bash
gh run list --workflow mobile-release.yml --limit 5
gh run watch RUN_ID --exit-status
```

Resultados esperados:

- APK e AAB anexados à GitHub Release;
- IPA anexado se o job iOS estiver habilitado;
- nenhum upload externo enquanto as variables de upload forem `false`.

### Release com upload para tracks de teste

1. Confirme Secrets e variables com `gh secret list` e `gh variable list`; os valores não são exibidos.
2. Ative `MOBILE_UPLOAD_ANDROID_TO_PLAY=true` para enviar ao Internal testing.
3. Ative `MOBILE_UPLOAD_IOS_TO_APP_STORE=true` para enviar ao App Store Connect.
4. Crie e publique a nova tag semântica.
5. Confira logs, artifacts e status nas duas lojas.
6. Faça a promoção para outros tracks manualmente depois da homologação.

## 6. Rollback e incidentes

- Não apague nem recrie uma tag já usada; publique uma versão corretiva com version code superior.
- Na Play Store, interrompa rollout gradual ou envie um novo AAB corrigido.
- Na App Store, remova o build da submissão quando possível ou envie um novo build.
- Revogue imediatamente API Keys, service accounts ou certificados expostos e substitua os Secrets do GitHub.
- A exposição da chave de upload Android exige o procedimento de reset da Play Console; a perda sem backup pode bloquear futuras atualizações.
- Registre SHA-256 dos artefatos, tag, run ID, tracks e responsáveis pela aprovação.

## 7. Checklist de publicação

- [ ] Worktree sem credenciais ou artefatos dinâmicos versionados.
- [ ] Testes, build Vite, `native:validate` e plugins passaram no mesmo commit.
- [ ] Tag, versão e version code são novos.
- [ ] APK, AAB e IPA têm assinatura válida.
- [ ] Builds release foram testados em aparelhos físicos.
- [ ] Login, multi-tenant, refresh, logout, offline e deep links foram retestados.
- [ ] Push foi testado em foreground, background e app encerrado, se habilitado.
- [ ] Política de privacidade, exclusão de conta e dados das lojas correspondem ao app real.
- [ ] Permissões sensíveis e acesso para revisão estão documentados.
- [ ] AAB passou pelo Internal e Closed testing antes de Production.
- [ ] Requisito de 12 testadores por 14 dias foi cumprido, se aplicável.
- [ ] IPA foi homologado no TestFlight antes do App Review.
- [ ] Upload automático foi habilitado somente depois do ensaio sem upload.
- [ ] Keystore, certificados, API Keys e service account têm backup seguro.

## Referências

- [NativePHP: publicação Android](https://nativephp.com/docs/mobile/4/publishing/android)
- [NativePHP: publicação iOS](https://nativephp.com/docs/mobile/4/publishing/ios)
- [Google Play: criar e configurar o app](https://support.google.com/googleplay/android-developer/answer/9859152)
- [Google Play: preparar e distribuir uma release](https://support.google.com/googleplay/android-developer/answer/9859348)
- [Google Play: requisitos para contas pessoais novas](https://support.google.com/googleplay/android-developer/answer/14151465)
- [Google Play Developer API](https://developers.google.com/android-publisher/getting_started)
- [Apple: criar profile App Store Connect](https://developer.apple.com/help/account/provisioning-profiles/create-an-app-store-provisioning-profile)
- [Apple: upload de builds](https://developer.apple.com/help/app-store-connect/manage-builds/upload-builds)
- [Apple: TestFlight](https://developer.apple.com/help/app-store-connect/test-a-beta-version/testflight-overview)
- [Apple: enviar para App Review](https://developer.apple.com/help/app-store-connect/manage-submissions-to-app-review/submit-an-app)
