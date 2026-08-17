# Uso das transmissões

Este documento explica como criar, publicar, acompanhar, moderar e encerrar uma transmissão no Nossa Casa. Para implantar a infraestrutura, consulte [Servidor principal e storage](media-core-deployment.md) e [Nó de mídia/MediaMTX](media-node-deployment.md).

## Visão geral

Cada transmissão pertence a uma igreja. Atualmente, uma igreja pode ter somente uma transmissão ativa por vez.

```text
Usuário de mídia cria a transmissão no painel
                     │
                     ▼
           Copia link e token para o OBS
                     │
                     ▼
              OBS publica no MediaMTX
                     │
          ┌──────────┴──────────┐
          ▼                     ▼
  Página pública/HLS      Gravação segmentada
          │                     │
   comentários            worker envia ao app
                                │
                                ▼
                  Galeria / categoria Transmissions
```

## Portas e firewall

Para publicar pelo OBS e assistir pelo navegador, use as seguintes portas:

| Porta | Protocolo | Exposição | Uso |
|---|---|---|---|
| `80/TCP` | HTTP | Pública | Acesso HTTP ao app e redirecionamento para HTTPS. |
| `443/TCP` | HTTPS | Pública | App e HLS por HTTPS. Recomendada como entrada do proxy reverso. |
| `1935/TCP` | RTMP | Pública | Entrada da transmissão enviada pelo OBS. |
| `8888/TCP` | HLS/HTTP | Pública somente sem proxy HTTPS | Player HLS direto. Com proxy reverso em `443`, não precisa ser aberta na internet. |
| `9997/TCP` | API HTTP do MediaMTX | Privada | Controle de paths pelo servidor principal. Libere somente para o IP do core ou pela VPN. Nunca encaminhe publicamente no roteador. |

Com a configuração recomendada, o roteador publica `80/TCP`, `443/TCP` e `1935/TCP`. O proxy reverso recebe HLS em `443/TCP` e encaminha internamente para `8888/TCP`. A porta `9997/TCP` fica restrita à rede privada/VPN entre o core e o nó de mídia.

As portas abaixo são opcionais e não precisam ser abertas para o fluxo atual com OBS por RTMP e reprodução por HLS:

| Porta | Protocolo | Quando usar |
|---|---|---|
| `8554/TCP` | RTSP | Publicação ou leitura por RTSP. |
| `8889/TCP` | WebRTC/WHEP | Sinalização WebRTC. |
| `8189/UDP` | WebRTC/ICE | Transporte de mídia WebRTC. |

Se o app estiver em HTTPS, não entregue HLS por `http://...:8888`, pois o navegador pode bloquear o conteúdo misto. Use um domínio HTTPS no `MEDIAMTX_PUBLIC_HLS_URL` e encaminhe esse domínio para a porta interna `8888`.

## Permissões

| Ação | Quem pode realizar |
|---|---|
| Assistir e ler comentários | Qualquer visitante |
| Comentar | Usuário autenticado vinculado à igreja |
| Destacar, remover destaque ou excluir comentários | `leader`, `media`, `admin`, `superadmin` e `system` vinculados à igreja |
| Abrir o controle de transmissões | `media`, `admin`, `superadmin` e `system` |
| Criar, encerrar e renovar o token | `media`, `admin`, `superadmin` e `system`, respeitando a igreja selecionada |

Usuários `system` podem selecionar e administrar diferentes igrejas. Os demais usuários autorizados operam a igreja à qual estão vinculados.

## Criar uma transmissão

1. Entre no painel administrativo.
2. No menu lateral, abra **Transmissões**, ou acesse `/dashboard/transmissoes`.
3. Se o usuário puder operar mais de uma igreja, selecione a igreja desejada.
4. Clique em **Nova transmissão**.
5. Informe um nome, por exemplo `Culto de domingo`.
6. Mantenha **Gravar e publicar nas mídias** marcado quando desejar arquivar a transmissão.
7. Clique em **Criar transmissão**.

Depois da criação, o painel mostrará:

- o estado da transmissão;
- o link completo de publicação para o OBS;
- o token de publicação;
- a data de início;
- se a gravação está ativada;
- a quantidade de gravações geradas;
- o acesso à página pública.

Enquanto existir uma transmissão ativa, o botão para criar outra ficará indisponível para aquela igreja. Encerre a transmissão atual antes de criar a próxima.

## Configurar o OBS Studio

Na tela de controle, copie o campo **Link de transmissão para OBS** e o **Token**.

No OBS:

1. Abra **Configurações → Transmissão**.
2. Em **Serviço**, selecione **Personalizado**.
3. Cole o link completo fornecido pelo painel no campo **Servidor**.
4. Deixe o campo **Chave de transmissão** vazio.
5. Salve e clique em **Iniciar transmissão**.

O link gerado pelo painel já contém o caminho e a credencial necessários para a publicação. Não compartilhe esse link nem o token em canais públicos.

### Configuração recomendada

Para maior compatibilidade com navegador, HLS e gravações:

- vídeo: H.264;
- áudio: AAC;
- intervalo de keyframe: 2 segundos;
- taxa de bits: escolha de acordo com a conexão de upload e a capacidade do servidor de mídia.

Como ponto inicial, use 1080p com 4–6 Mbps ou 720p com 2,5–4 Mbps. Garanta que o upload disponível seja superior à taxa configurada e faça um teste antes do evento.

## Iniciar e acompanhar

Quando o MediaMTX aceitar a publicação do OBS, ele notificará o app e o estado mudará automaticamente para `live`. O painel atualiza as informações periodicamente.

Para validar:

1. espere o painel indicar que a transmissão está ao vivo;
2. clique em **Abrir página pública**;
3. confira vídeo e áudio em outro dispositivo e, de preferência, em outra conexão;
4. verifique se o widget de transmissão aparece no site da igreja.

Quando uma igreja possui transmissão ativa, o cabeçalho público exibe um acesso para a página `/transmissoes/{id}`.

Pode existir um pequeno atraso entre iniciar o OBS e o player ficar disponível. Enquanto o servidor se conecta, a página pública se atualiza automaticamente.

## Página pública e comentários

A página pública contém o player e a área de comentários.

- visitantes não autenticados podem assistir e ler os comentários;
- visitantes podem usar **Entre para comentar** para acessar a conta;
- usuários autenticados e vinculados à igreja podem enviar comentários;
- os comentários e o estado da transmissão são atualizados automaticamente;
- comentários destacados aparecem antes dos demais e recebem indicação visual.

### Moderação

Usuários autorizados veem ações em cada comentário:

- **Destacar**: fixa o comentário no topo;
- **Remover destaque**: devolve o comentário à ordenação normal;
- **Remover comentário**: exclui o comentário após confirmação.

O conteúdo deve ser moderado na página pública da própria transmissão. As permissões são limitadas à igreja do usuário, exceto para `system`.

## Renovar o token

Use **Renovar** quando houver suspeita de vazamento ou quando for necessário invalidar a credencial atual.

1. Abra `/dashboard/transmissoes`.
2. Localize a transmissão.
3. Clique em **Renovar** ao lado do token.
4. Confirme a operação.
5. Copie novamente o link/token atualizado para o OBS.

A renovação desconecta ou impede a continuação da publicação feita com a credencial anterior. Se a transmissão estiver em andamento, será necessário atualizar o OBS e iniciar novamente.

## Encerrar uma transmissão

1. Primeiro, encerre a publicação no OBS.
2. Aguarde o painel deixar de mostrar o estado ao vivo.
3. No painel, clique em **Encerrar**.
4. Confirme a operação.

Encerrar libera a igreja para criar uma nova transmissão. A página pública passa a informar que a transmissão foi encerrada.

Se o OBS perder conexão temporariamente, aguarde a reconexão antes de encerrar pelo painel. Cada interrupção pode resultar em mais de um segmento de gravação.

## Gravações

Quando **Gravar e publicar nas mídias** estiver ativado, o MediaMTX cria segmentos e o worker os envia ao armazenamento definitivo.

Após o processamento:

- a gravação recebe o estado `ready`;
- uma mídia aprovada é criada para a igreja;
- a mídia entra automaticamente na categoria `Transmissions`;
- ela fica disponível na galeria para reprodução e download, como as demais mídias.

O processamento não precisa terminar imediatamente após o encerramento. Em uma instalação com nó remoto, o arquivo permanece na máquina de mídia até o storage confirmar a gravação e o servidor principal confirmar o registro.

## Estados apresentados

| Estado | Significado |
|---|---|
| `ready` | Criada e aguardando o publicador |
| `live` | O MediaMTX está recebendo a transmissão |
| `offline` | A publicação foi interrompida ou finalizada no servidor de mídia |
| `stopped` | Encerrada manualmente no painel |
| `failed` | O servidor registrou uma falha na transmissão |

O estado `offline` não significa necessariamente que a gravação foi perdida. Os segmentos concluídos continuam sendo processados pelo worker.

## Checklist antes do evento

- confirme que a igreja correta está selecionada;
- crie a transmissão e mantenha a gravação ativada, se necessária;
- configure o OBS com o link atual;
- faça um teste de vídeo, áudio e estabilidade da conexão;
- abra a página pública em outro dispositivo;
- teste um comentário com usuário autenticado;
- confirme quem fará a moderação;
- verifique espaço em disco e o estado do `media-worker` quando usar um nó remoto.

## Solução de problemas

### O OBS não conecta

- copie novamente o link completo do painel;
- confirme que a chave do OBS está vazia;
- verifique se o token não foi renovado depois da configuração do OBS;
- confirme que a porta RTMP `1935/TCP` está acessível;
- verifique os logs do MediaMTX.

### O painel não muda para ao vivo

- confirme que o OBS realmente está enviando dados;
- verifique a comunicação do MediaMTX com `MEDIA_CORE_URL`;
- confira se `MEDIA_WORKER_TOKEN` é igual no app principal e no nó de mídia;
- examine os logs do `mediamtx` e do app principal.

### O player não abre

- confira `MEDIAMTX_PUBLIC_HLS_URL` no servidor principal;
- confirme que o domínio HLS possui HTTPS válido;
- verifique CORS e o proxy que encaminha para a porta `8888`;
- aguarde alguns segundos para a geração inicial do HLS.

### A gravação não apareceu na galeria

- confirme que a opção de gravação estava ativada;
- consulte os logs do `media-worker`;
- execute `php artisan queue:failed` no ambiente correspondente;
- confira espaço livre no nó de mídia e no storage definitivo;
- em um nó remoto, valide HTTPS, checksum e o segredo compartilhado.

Para comandos de diagnóstico, recuperação da fila e configuração de rede, consulte [Nó de mídia em outra máquina](media-node-deployment.md).
