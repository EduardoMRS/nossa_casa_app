```text
                         ┌──────────────────┐
                         │   Laravel 13     │
                         │   + Vue 3        │
                         │                  │
                         │ Usuários         │
                         │ Lives            │
                         │ Permissões       │
                         │ Gravações        │
                         └────────┬─────────┘
                                  │
                           API / Webhooks
                                  │
                                  ▼
┌─────────┐ RTMP  ┌──────────────────────────┐
│   OBS   │──────▶│       MediaMTX           │
└─────────┘       │                          │
                  │ RTMP ingest              │
                  │ WebRTC / HLS playback    │
                  │ Recording                │
                  └──────┬───────────┬───────┘
                         │           │
                    LIVE │           │ arquivos
                         │           ▼
                         │      /recordings
                         │           │
                         ▼           ▼
                    Browser       Worker Laravel
                  Vue <video>         │
                                      ▼
                              S3 / MinIO / Storage
                                      │
                                      ▼
                                  Laravel
                                vídeos gravados
```

Hoje eu começaria com **MediaMTX** em vez de montar Nginx-RTMP + FFmpeg manualmente. Ele suporta publicação RTMP, distribuição via WebRTC/HLS, gravação, playback, autenticação externa e hooks, exatamente as peças que você precisa. ([MediaMTX][1])

## 1. Como ficaria uma transmissão

Na sua aplicação, o usuário cria uma live:

```text
POST /api/lives
```

O Laravel cria algo parecido com:

```text
id: 182
uuid: a8d4...
user_id: 32
title: "Evento XYZ"
stream_key: 3f754120...
status: created
```

Então você apresenta para o usuário:

```text
Servidor:
rtmp://stream.seudominio.com/live

Chave:
3f754120-xxxx-xxxx-xxxx
```

No OBS:

```text
Service: Custom
Server: rtmp://stream.seudominio.com/live
Stream Key: 3f754120-xxxx-xxxx-xxxx
```

Internamente isso vira algo como:

```text
rtmp://stream.seudominio.com/live/3f754120-xxxx
```

O MediaMTX recebe RTMP nativamente. ([MediaMTX][2])

---

# 2. Não deixe qualquer pessoa transmitir

Essa é uma parte importante da arquitetura.

O MediaMTX consegue delegar a autenticação para um servidor HTTP externo. Portanto, ele pode perguntar ao seu Laravel:

> Este usuário pode publicar nesse path?

A documentação atual permite autenticação HTTP externa e envia informações como:

```json
{
    "user": "",
    "password": "",
    "token": "...",
    "action": "publish",
    "path": "live/3f754120...",
    "protocol": "rtmp",
    "ip": "..."
}
```

Seu Laravel simplesmente responde `2xx` para aceitar ou outro status para negar. ([MediaMTX][3])

Eu faria:

```text
POST /api/internal/media/auth
```

E verificaria:

```php
$live = LiveStream::where('stream_key', $streamKey)->first();

if (
    !$live ||
    !$live->enabled ||
    $live->status === 'finished'
) {
    abort(403);
}

return response()->noContent();
```

Isso evita depender apenas de uma URL obscura.

---

# 3. Estrutura do banco

Eu começaria com algo assim:

```text
live_streams
────────────────────────────
id
uuid
user_id

title
description

stream_key
stream_path

status
scheduled_at
started_at
ended_at

thumbnail_path

created_at
updated_at
```

Status:

```text
draft
scheduled
ready
live
processing
finished
failed
```

Depois:

```text
recordings
────────────────────────────
id
live_stream_id

disk
path
filename

duration
size
mime_type

status

started_at
ended_at

created_at
updated_at
```

Onde `recordings.status` pode ser:

```text
recording
waiting_upload
uploading
processing
ready
failed
```

Isso vai permitir, inclusive, separar uma transmissão em várias gravações caso o OBS desconecte e reconecte.

---

# 4. Quando o OBS começa a transmitir

Essa é uma das coisas legais do MediaMTX.

Ele possui hooks como:

```text
runOnAvailable
runOnUnavailable
runOnOnline
runOnOffline
runOnRecordSegmentCreate
runOnRecordSegmentComplete
```

Então o servidor de mídia pode avisar seu Laravel automaticamente quando uma transmissão começar ou terminar. ([MediaMTX][4])

Por exemplo:

```yaml
pathDefaults:

  runOnOnline: >
    curl -X POST
    http://app/api/internal/media/online
    -d "path=$MTX_PATH"

  runOnOffline: >
    curl -X POST
    http://app/api/internal/media/offline
    -d "path=$MTX_PATH"
```

Seu Laravel receberia:

```text
POST /api/internal/media/online
```

e faria:

```php
$live->update([
    'status' => 'live',
    'started_at' => now(),
]);
```

Quando parar:

```php
$live->update([
    'status' => 'processing',
    'ended_at' => now(),
]);
```

E seu frontend Vue recebe isso por:

```text
Laravel Reverb
        ↓
WebSocket
        ↓
Vue
```

Então a página pode mudar automaticamente:

```text
AGUARDANDO TRANSMISSÃO

        ↓ OBS começa

🔴 AO VIVO

        ↓ OBS encerra

PROCESSANDO VÍDEO

        ↓ upload termina

▶ ASSISTIR GRAVAÇÃO
```

---

# 5. Como assistir a transmissão na página Vue

**Não envie RTMP para o navegador.**

RTMP deve ficar basicamente:

```text
OBS → MediaMTX
```

Para o navegador use:

```text
MediaMTX → WebRTC → navegador
```

ou:

```text
MediaMTX → HLS → navegador
```

O MediaMTX suporta ambos e permite incorporá-los em sites externos. ([MediaMTX][5])

Eu usaria:

### WebRTC

Para transmissão realmente ao vivo:

```text
OBS
 ↓ RTMP
MediaMTX
 ↓ WebRTC
Vue
```

A latência pode ficar muito menor que HLS.

Então na página:

```text
/live/182
```

você poderia ter:

```vue
<video
    ref="video"
    autoplay
    playsinline
    controls
/>
```

E conectar através do endpoint WHEP do MediaMTX.

O próprio MediaMTX fornece suporte para leitura WebRTC em browser e integração via JavaScript. ([MediaMTX][5])

---

# 6. Ou use HLS

Outra alternativa muito boa:

```text
OBS
 ↓ RTMP
MediaMTX
 ↓ HLS
hls.js
 ↓
Vue
```

Por exemplo:

```text
https://stream.seudominio.com/hls/live/STREAM_KEY/index.m3u8
```

E:

```bash
npm install hls.js
```

Vue:

```vue
<script setup>
import Hls from 'hls.js'
import { onMounted, ref } from 'vue'

const video = ref(null)

onMounted(() => {
    const url =
        'https://stream.seudominio.com/live/abc/index.m3u8'

    if (Hls.isSupported()) {
        const hls = new Hls()

        hls.loadSource(url)
        hls.attachMedia(video.value)
    } else {
        video.value.src = url
    }
})
</script>

<template>
    <video
        ref="video"
        controls
        autoplay
        playsinline
    />
</template>
```

O MediaMTX documenta diretamente o uso de HLS + `hls.js` dessa maneira. ([MediaMTX][5])

A diferença principal:

```text
                WebRTC          HLS

Latência        ~baixa          maior
Browser         ✓               ✓
Escalabilidade  boa             excelente
CDN             mais difícil    muito fácil
Configuração    mais complexa   simples
```

Para seu sistema eu disponibilizaria **WebRTC para live inicialmente**, podendo ter HLS como fallback.

---

# 7. E a gravação?

O MediaMTX consegue gravar a transmissão recebida diretamente. ([MediaMTX][6])

Você pode montar um volume Docker:

```yaml
volumes:
  - ./recordings:/recordings
```

E configurar algo aproximadamente assim:

```yaml
pathDefaults:
  record: yes
  recordPath: /recordings/%path/%Y-%m-%d_%H-%M-%S
```

Então:

```text
OBS
 ↓
MediaMTX
 ├── WebRTC → viewers
 └── Recording
        ↓
 /recordings/live/abc/...
```

Isso é importante porque não há necessidade de:

```text
RTMP
 ↓
FFmpeg #1 → HLS
 ↓
FFmpeg #2 → MP4
```

para o MVP.

Você reduz consideravelmente a quantidade de processos.

---

# 8. Gravação segmentada é melhor

Eu evitaria produzir diretamente algo como:

```text
live.mp4
```

durante uma transmissão de 4 horas.

Prefira segmentos.

Algo como:

```text
recordings/
   live_182/
      2026-08-13_20-00-00.mp4
      2026-08-13_20-15-00.mp4
      2026-08-13_20-30-00.mp4
      ...
```

O MediaMTX consegue disparar `runOnRecordSegmentComplete` assim que cada segmento é finalizado. ([MediaMTX][4])

Essa característica encaixa perfeitamente no seu worker.

---

# 9. Worker Laravel

Quando um segmento terminar:

```text
MediaMTX
   ↓
runOnRecordSegmentComplete
   ↓
POST Laravel
   ↓
RecordingCompletedJob
```

Laravel:

```php
RecordingCompletedJob::dispatch(
    liveId: $live->id,
    path: $request->segment_path,
);
```

Worker:

```text
RecordingCompletedJob
       ↓
verifica arquivo
       ↓
move/upload
       ↓
S3 / MinIO
       ↓
salva recording
       ↓
remove arquivo temporário
```

Algo conceitualmente como:

```php
class UploadRecording implements ShouldQueue
{
    public function handle(): void
    {
        Storage::disk('s3')->put(
            $this->destination,
            fopen($this->localPath, 'r')
        );

        $this->recording->update([
            'disk' => 's3',
            'path' => $this->destination,
            'status' => 'ready',
        ]);

        unlink($this->localPath);
    }
}
```

---

# 10. Mas eu faria uma diferença importante

Você comentou:

> posteriormente será enviado por worker Laravel para a aplicação principal

Eu **não enviaria o vídeo para dentro do servidor Laravel principal**, se for possível evitar.

Faria:

```text
                         ┌──────────────┐
                         │     S3       │
Media Server ──upload───▶│   / MinIO    │
                         └──────┬───────┘
                                │
              ┌─────────────────┴─────────────┐
              │                               │
           Laravel                         Browser
         metadata                            vídeo
```

Laravel guarda somente:

```text
live_id
path
size
duration
thumbnail
etc
```

Por exemplo:

```text
videos/2026/08/live-182/recording.mp4
```

O vídeo não precisa passar:

```text
Media Server
     ↓
Laravel server
     ↓
S3
```

O ideal seria:

```text
Media Server
     ↓
S3
```

e o worker Laravel apenas coordena.

---

# 11. A reprodução posterior

Depois que a live terminou:

```text
/live/182
```

poderia passar automaticamente de:

```text
LivePlayer.vue
```

para:

```text
VideoPlayer.vue
```

O Laravel retornaria:

```json
{
    "id": 182,
    "title": "Evento XYZ",
    "status": "finished",
    "recording": {
        "url": "https://cdn.exemplo.com/videos/live-182.mp4"
    }
}
```

Então:

```vue
<video controls>
    <source :src="live.recording.url" type="video/mp4">
</video>
```

Para o começo isso é suficiente.

---

# 12. Depois dá para evoluir para VOD HLS

Se os vídeos ficarem grandes, por exemplo:

```text
1h
2h
3h
```

eu não ficaria apenas com MP4 progressivo.

O worker poderia executar:

```text
gravação
   ↓
FFmpeg
   ↓
VOD HLS
```

Gerando:

```text
master.m3u8

1080p/
    index.m3u8
    segment001.ts
    ...

720p/
    index.m3u8
    ...

480p/
    index.m3u8
    ...
```

Assim você consegue adaptive bitrate:

```text
1080p
720p
480p
```

dependendo da conexão do usuário.

FFmpeg continua sendo excelente para essa etapa de conversão/transcodificação; seu suporte a protocolos e formatos inclui RTMP e HLS. ([FFmpeg][7])

---

# 13. E múltiplas lives?

Isso já se encaixa naturalmente.

Imagine:

```text
OBS Eduardo
rtmp://stream.site.com/live/AAA

OBS Cliente 2
rtmp://stream.site.com/live/BBB

OBS Cliente 3
rtmp://stream.site.com/live/CCC
```

MediaMTX:

```text
live/AAA
live/BBB
live/CCC
```

E:

```text
/live/1 → AAA
/live/2 → BBB
/live/3 → CCC
```

O MediaMTX é projetado justamente para publicar e ler múltiplos paths de streams e possui documentação específica de escalabilidade. ([MediaMTX][8])

---

# 14. Uma preocupação maior: transcoding

Aqui tem uma decisão importante.

Suponha que o OBS envie:

```text
1920x1080
8 Mbps
```

Se 100 pessoas assistirem:

```text
8 Mbps × 100

≈ 800 Mbps
```

Seu servidor rapidamente vira gargalo.

Além disso, um usuário com internet ruim talvez não consiga assistir.

Então posteriormente você pode criar:

```text
               1080p 6 Mbps
              /
OBS → FFmpeg ── 720p 3 Mbps
              \
               480p 1 Mbps
```

Isso é o chamado ABR:

```text
Adaptive Bitrate Streaming
```

Mas eu **não implementaria isso logo na primeira versão**.

Primeiro:

```text
OBS H264/AAC
        ↓
MediaMTX
        ↓
WebRTC/HLS
```

sem transcodificação.

Isso consome muito menos CPU.

---

# 15. OBS deveria usar H.264 + AAC

Para manter compatibilidade:

```text
Video:
H.264

Audio:
AAC
```

Mesmo existindo suporte moderno a outros codecs em várias partes do ecossistema, H.264/AAC ainda simplifica bastante a compatibilidade entre ingestão, navegador, HLS e gravação. O MediaMTX hoje suporta vários codecs via RTMP, inclusive H.264 e AAC. ([MediaMTX][9])

---

# 16. Infraestrutura que eu faria

Como você já usa Docker, eu montaria:

```text
server/
│
├── docker-compose.yml
│
├── media/
│   └── mediamtx.yml
│
├── recordings/
│
└── nginx/
    └── ...
```

Containers:

```text
┌───────────────────────┐
│ Laravel               │
│ PHP-FPM               │
└───────────────────────┘

┌───────────────────────┐
│ Queue Worker          │
│ php artisan queue:work│
└───────────────────────┘

┌───────────────────────┐
│ Redis                 │
└───────────────────────┘

┌───────────────────────┐
│ MediaMTX              │
│                       │
│ :1935 RTMP            │
│ :8888 HLS             │
│ :8889 WebRTC          │
└───────────────────────┘

┌───────────────────────┐
│ MinIO / S3            │
└───────────────────────┘
```

Você pode inclusive colocar o MediaMTX em **outro servidor físico**.

Eu acho melhor.

---

# 17. Eu separaria os domínios

Por exemplo:

```text
app.seudominio.com
```

Laravel + Vue.

```text
stream.seudominio.com
```

MediaMTX.

```text
cdn.seudominio.com
```

gravações.

Então o usuário vê tudo dentro:

```text
https://app.seudominio.com/live/182
```

mas internamente o player está consumindo:

```text
https://stream.seudominio.com/...
```

Enquanto vídeos antigos vêm de:

```text
https://cdn.seudominio.com/...
```

---

# 18. Segurança de visualização

Também não faria:

```text
stream.site.com/live/182
```

aberto para qualquer pessoa.

O MediaMTX consegue autenticar não apenas `publish`, mas também ações de `read` e `playback`, inclusive por HTTP externo ou JWT. ([MediaMTX][3])

Então podemos ter:

```text
Usuário abre /live/182
        ↓
Laravel verifica
        ↓
tem permissão?
        ↓
gera token temporário
        ↓
Vue conecta MediaMTX
        ↓
MediaMTX valida token
```

Isso vai ser especialmente interessante caso futuramente existam:

```text
lives privadas
lives pagas
lives somente para clientes
lives somente para membros
```

---

# 19. Arquitetura final que eu indicaria

Eu estruturaria seu projeto assim:

```text
                         INTERNET

                            │
                   ┌────────┴────────┐
                   │                 │
                  OBS             Browser
                   │                 │
                RTMP             HTTPS/WSS
                   │                 │
                   ▼                 ▼

             ┌───────────┐     ┌─────────────┐
             │ MediaMTX  │◀────│ Laravel/Vue │
             └─────┬─────┘     └──────┬──────┘
                   │                  │
          ┌────────┴─────────┐        │
          │                  │        │
       WebRTC              record     │
       / HLS                  │        │
          │                   ▼        │
          │             Local SSD      │
          │                   │        │
          │                   ▼        │
          │                Queue       │
          │                   │        │
          │                   ▼        │
          │               S3/MinIO ◀───┘
          │
          ▼
       Browser
```

É uma arquitetura que começa pequena mas não te prende quando o número de transmissões crescer.

---

## A implementação que eu faria no seu Laravel 13

Eu dividiria em módulos:

```text
app/
├── Models/
│   ├── LiveStream.php
│   └── Recording.php
│
├── Http/Controllers/
│   ├── LiveStreamController.php
│   └── MediaServerController.php
│
├── Jobs/
│   ├── ProcessRecording.php
│   ├── UploadRecording.php
│   └── GenerateVideoThumbnail.php
│
├── Events/
│   ├── LiveStarted.php
│   ├── LiveStopped.php
│   └── RecordingReady.php
│
└── Services/
    └── Media/
        ├── MediaServer.php
        └── MediaMtxService.php
```

Vue:

```text
resources/js/
├── pages/
│   └── Live/
│       ├── Index.vue
│       ├── Create.vue
│       ├── Show.vue
│       └── Studio.vue
│
└── components/
    └── Media/
        ├── LivePlayer.vue
        ├── RecordedPlayer.vue
        ├── StreamStatus.vue
        └── ObsConfiguration.vue
```

E `Studio.vue` poderia mostrar exatamente:

```text
┌──────────────────────────────────────────────┐
│ Minha transmissão                           │
│                                              │
│ Status               ● Aguardando OBS       │
│                                              │
│ Servidor RTMP                               │
│ rtmp://stream.site.com/live          [copy] │
│                                              │
│ Chave                                       │
│ ••••••••••••••••••••                [copy] │
│                                     [mostrar]│
│                                              │
│ ─────────────────────────────────────────── │
│                                              │
│         PREVIEW DA TRANSMISSÃO              │
│                                              │
│            [ vídeo ]                        │
│                                              │
└──────────────────────────────────────────────┘
```

E essa mesma arquitetura resolve exatamente o requisito de **várias transmissões simultâneas + assistir dentro da aplicação + gravar + processar via worker + disponibilizar posteriormente como vídeo**.

Minha escolha para a primeira versão seria **Laravel 13 + Vue 3 + Redis Queue + Reverb + MediaMTX + H.264/AAC + WebRTC para live + MP4 para gravações + S3/MinIO para armazenamento**. Quando houver demanda real de banda/audiência, aí introduziria FFmpeg para ABR e eventualmente CDN.