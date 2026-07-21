<?php

namespace App\Services;

use App\Models\AiModel;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AiProvider
{
    private $client;
    private $provider;
    private $model;
    private $models = [];
    private $headers;
    private $messages = [];
    private $apiUrl;

    const CONTENT_TYPE_JSON = 'application/json';

    public function __construct(string $provider = 'openrouter', ?string $model = null)
    {
        $this->client = new Client();
        $this->setProvider($provider, $model);
    }

    /**
     * Define o provedor e modelo a ser usado
     *
     * @param string $provider Nome do provedor (openai, openrouter, gemini)
     * @param string|null $model Nome do modelo específico
     */
    public function setProvider(string $provider, string $model = null): self
    {
        if (!isset(config('services.ia')[$provider])) {
            throw new InvalidArgumentException("Provedor '$provider' não suportado. Provedores disponíveis: " . implode(', ', array_keys(config('services.ia'))));
        }

        $this->provider = $provider;
        
        $this->models = AiModel::provider($provider);

        // Caso não existam modelos cadastrados, busca diretamente na API
        if (empty($this->models)) {
            try {
                $response = $this->getModels();

                $this->models = collect($response['data'] ?? [])
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::warning("Falha ao buscar modelos do provider '{$provider}': {$e->getMessage()}");
            }
        }

        // Fallback para configuração
        if (empty($this->models)) {
            $this->models = config("services.ia.$provider.models", []);
        }

        // Se ainda estiver vazio, não há o que fazer
        if (empty($this->models)) {
            throw new \RuntimeException("Nenhum modelo disponível para o provider '{$provider}'.");
        }

        // Modelo padrão
        $model ??= $this->models[0];

        // Caso o modelo informado não exista, utiliza o primeiro disponível
        if (!in_array($model, $this->models, true)) {
            Log::warning("Modelo '{$model}' não encontrado para '{$provider}'. Utilizando '{$this->models[0]}'.");
            $model = $this->models[0];
        }

        $this->model = $model;
        $this->apiUrl = config("services.ia.{$this->provider}.url");
        $this->setupHeaders();

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public static function getProviders(): array
    {
        return array_keys(array_filter(config('services.ia'), function ($config) {
            return isset($config['token']) && !empty($config['token']);
        }));
    }

    /**
     * Retorna a lista de modelos suportados pelo endpoint correspondente de cada API
     */
    public function getModels(): array
    {
        $config = config("services.ia.{$this->provider}");
        $headers = [
            'Content-Type' => self::CONTENT_TYPE_JSON,
        ];

        $url = config("services.ia.{$this->provider}.list_models") ?? 'https://api.openai.com/v1/models';
        $headers['Authorization'] = 'Bearer ' . $config['token'];

        try {
            $request = new Request('GET', $url, $headers);
            $response = $this->client->send($request);
            $body = json_decode($response->getBody()->getContents(), true);
            if ($this->provider === 'gemini') {
                $formattedModels = [];
                if (isset($body['data'])) {
                    foreach ($body['data'] as $m) {
                        $cleanId = str_replace('models/', '', $m['id']);
                        
                        // Captura dados adicionais da estrutura do Gemini para o controller usar
                        $inputModalities = $m['input_modalities'] ?? ['text'];
                        $outputModalities = $m['output_modalities'] ?? ['text'];

                        $formattedModels[] = [
                            'id' => $cleanId,
                            'name' => $m['display_name'] ?? $cleanId,
                            'context_length' => $m['input_token_limit'] ?? 0,
                            'architecture' => [
                                'input_modalities' => array_map('strtolower', $inputModalities),
                                'output_modalities' => array_map('strtolower', $outputModalities)
                            ],
                            'pricing' => [
                                'prompt' => null,
                                'completion' => null
                            ]
                        ];
                    }
                }
                return ['data' => $formattedModels];
            }

            return $body;
        } catch (RequestException $e) {
            Log::error("Erro ao obter modelos do provedor {$this->provider}: " . $e->getMessage());
            return ['data' => []];
        }
    }

    /**
     * Define o modelo atual para permitir re-tentativas com outros modelos
     *
     * @param string $model Nome do modelo
     * @return self
     */
    public function setModel(string $model): self
    {
        if (!isset(config("services.ia")[$this->provider])) {
            throw new InvalidArgumentException("Provedor '{$this->provider}' não encontrado");
        }

        if (!in_array($model, $this->models)) {
            throw new InvalidArgumentException("Modelo '$model' não suportado pelo provedor '{$this->provider}'. Modelos disponíveis: " . implode(', ', $this->models));
        }

        $this->model = $model;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Configura os headers baseado no provedor
     */
    private function setupHeaders(): void
    {
        switch ($this->provider) {
            case 'openai':
                $this->setupOpenAiHeaders();
                break;
            case 'openrouter':
                $this->setupOpenRouterHeaders(); 
                break;
            case 'gemini':
                $this->setupGeminiHeaders();
                break;
            default:
                throw new InvalidArgumentException("Headers não configurados para o provedor: " . $this->provider);
        }
    }

    /**
     * Configura headers para OpenAI
     */
    private function setupOpenAiHeaders(): void
    {
        $this->headers = [
            'Content-Type' => self::CONTENT_TYPE_JSON,
            'Authorization' => 'Bearer ' . config('services.ia.openai.token'),
            'HTTP-Referer' => url('/'),
        ];
    }

    /**
     * Configura headers para OpenRouter
     */
    private function setupOpenRouterHeaders(): void
    {
        $this->headers = [
            'Content-Type' => self::CONTENT_TYPE_JSON,
            'Authorization' => 'Bearer ' . config('services.ia.openrouter.token'),
            'HTTP-Referer' => url('/'),
            'X-OpenRouter-Title' => config('app.name')
        ];
    }

    /**
     * Configura headers para Gemini
     */
    private function setupGeminiHeaders(): void
    {
        $this->headers = [
            'Content-Type' => self::CONTENT_TYPE_JSON,
            'Authorization' => 'Bearer ' . config('services.ia.gemini.token'),
            'HTTP-Referer' => url('/'),
        ];
    }

    /**
     * Adiciona uma mensagem do sistema
     * 
     * @param string $content Conteúdo da mensagem
     */
    public function system(string $content): self
    {
        $this->addMessage('system', $content);
        return $this;
    }

    /**
     * Adiciona uma mensagem do usuário
     * 
     * @param string $content Conteúdo da mensagem
     */
    public function user(string $content): self
    {
        $this->addMessage('user', $content);
        return $this;
    }

    /**
     * Adiciona uma mensagem do assistente
     * 
     * @param string $content Conteúdo da mensagem
     */
    public function assistant(string $content): self
    {
        $this->addMessage('assistant', $content);
        return $this;
    }

    /**
     * Adiciona uma mensagem à conversa
     * 
     * @param string $role Papel da mensagem (system, user, assistant)
     * @param string|array $content Conteúdo da mensagem
     */
    private function addMessage(string $role, $content): void
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $content
        ];
    }

    /**
     * Adiciona uma imagem como mensagem do usuário
     */
    public function image(string $fileContent, ?string $altText = null): self
    {
        $fileUrl = $this->getDataUri($fileContent, 'image/jpeg');
        if (!$fileUrl) return $this;

        $content = [
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $fileUrl
                ]
            ]
        ];

        if ($altText) {
            array_unshift($content, ['type' => 'text', 'text' => $altText]);
        }

        $this->addMessage('user', $content);
        return $this;
    }

    /**
     * Adiciona um áudio como mensagem do usuário (Para modelos compatíveis com input_audio)
     */
    public function audio(string $audioContent, ?string $description = null): self
    {   
        $fileUrl = $this->getDataUri($audioContent, 'audio/wav');
        if (!$fileUrl) return $this;

        // O padrão input_audio exige apenas o bloco purificado do base64 (sem o cabeçalho data:)
        $base64Data = $fileUrl;
        if (strpos($fileUrl, 'data:') === 0) {
            $parts = explode(',', $fileUrl);
            $base64Data = end($parts);
        }

        $content = [
            [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => $base64Data,
                    'format' => 'wav',
                ]
            ]
        ];

        if ($description) {
            array_unshift($content, ['type' => 'text', 'text' => $description]);
        }

        $this->addMessage('user', $content);
        return $this;
    }

    /**
     * Adiciona um arquivo como mensagem do usuário
     */
    public function file(string $fileContent, ?string $description = null): self
    {       
        $fileUrl = $this->getDataUri($fileContent, 'application/octet-stream');
        if (!$fileUrl) return $this;

        $content = [
            [
                'type' => 'file',
                'file_url' => [
                    'url' => $fileUrl
                ]
            ]
        ];

        if ($description) {
            array_unshift($content, ['type' => 'text', 'text' => $description]);
        }

        $this->addMessage('user', $content);
        return $this;
    }

    /**
     * Adiciona um vídeo como mensagem do usuário
     */
    public function video(string $videoContent, ?string $description = null): self
    {
        $fileUrl = $this->getDataUri($videoContent, 'video/mp4');
        if (!$fileUrl) return $this;

        $content = [
            [
                'type' => 'video_url',
                'video_url' => [
                    'url' => $fileUrl
                ]
            ]
        ];

        if ($description) {
            array_unshift($content, ['type' => 'text', 'text' => $description]);
        }

        $this->addMessage('user', $content);
        return $this;
    }

    /**
     * Executa a requisição para o provedor de IA
     * 
     * Tenta o modelo configurado primeiro; se houver um status 404 ou um erro
     * de requisição, tenta os demais modelos disponíveis para o provedor.
     *
     * @return array Resposta decodificada da API
     */
    public function run(): array
    {
        $models = AiModel::provider($this->provider);
        $orderedModels = array_merge([$this->model], array_values(array_diff($models, [$this->model])));
        $attempted = [];
        $lastExceptionMessage = null;

        foreach ($orderedModels as $model) {
            try {
                $this->setModel($model);
            } catch (InvalidArgumentException $e) {
                throw $e;
            }
            
            $attempted[] = $model;

            $body = $this->prepareBody();
            try {
                $request = new Request('POST', $this->apiUrl, $this->headers, $body);
                $response = $this->client->sendAsync($request)->wait();
                $status = $response->getStatusCode();

                // Se sucesso (2xx), normaliza e retorna
                if ($status >= 200 && $status < 300) {
                    $result = json_decode($response->getBody()->getContents(), true);
                    $normalized = $this->normalizeResponse($result);
                    $normalized['attempted_models'] = $attempted;
                    // Log::debug("Successful response from provider '{$this->provider}' using model '$model'. Attempted models: " . implode(', ', $attempted), ['response' => $normalized]);
                    return $normalized;
                }

                // Se 404 ou outro status de erro, tenta o próximo modelo
                if ($status === 404) {
                    continue;
                }

                if($status >= 400 && $status < 600) {
                    $lastExceptionMessage = "Received HTTP status $status for model '$model'";
                    continue;
                }


                // Para outros status não-2xx também tentar próximo modelo
                continue;
            } catch (RequestException $e) {
                $lastExceptionMessage = $e->getMessage();
                continue;
            } catch (\Exception $e) {
                $lastExceptionMessage = $e->getMessage();
                continue;
            }
        }

        // Todas as tentativas falharam — retorna informação de erro e modelos tentados
        $errorResponse = [
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => '',
            'usage' => null,
            'raw_response' => null,
            'error' => $lastExceptionMessage ?? 'All attempts failed with non-2xx responses.',
            'attempted_models' => $attempted
        ];
        
        return $errorResponse;
    }

    /**
     * Prepara o body da requisição baseado no provedor
     * 
     * @return string JSON do body
     */
    private function prepareBody(): string
    {
        switch ($this->provider) {
            case 'openai':
                return json_encode([
                    "model" => $this->model,
                    "messages" => $this->messages
                ]);

            case 'openrouter':
                return json_encode([
                    "model" => $this->model,
                    "messages" => $this->messages
                ]);
            case 'gemini':
                return json_encode([
                    "model" => $this->model,
                    "messages" => $this->messages
                ]);
            default:
                throw new InvalidArgumentException("Formato de body não implementado para o provedor: " . $this->provider);
        }
    }

    /**
     * Normaliza a resposta para um formato padrão
     * 
     * @param array $response Resposta original da API
     * @return array Resposta normalizada
     */
    private function normalizeResponse(array $response): array
    {
        // Verifica se é uma resposta no formato OpenAI/AtlasCloud
        if (isset($response['choices'][0]['message']['content'])) {
            return $this->normalizeOpenAiLikeResponse($response);
        }

        // Fallback para provedores específicos
        switch ($this->provider) {
            case 'openai':
                return $this->normalizeOpenAiResponse($response);

            case 'google':
                return $this->normalizeGoogleResponse($response);

            case 'anthropic':
                return $this->normalizeAnthropicResponse($response);

            default:
                return $this->getDefaultResponse($response);
        }
    }

    /**
     * Normaliza resposta no formato OpenAI (inclui AtlasCloud e similares)
     */
    private function normalizeOpenAiLikeResponse(array $response): array
    {
        // Extrai o conteúdo da resposta
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Identifica o provedor real da resposta (pode ser diferente do configurado)
        $responseProvider = $response['provider'] ?? $this->provider;
        $responseModel = $response['model'] ?? $this->model;
        
        return [
            'provider' => $responseProvider,
            'model' => $responseModel,
            'content' => $content,
            'usage' => $response['usage'] ?? null,
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
            'id' => $response['id'] ?? null,
            'created' => $response['created'] ?? null,
            'raw_response' => $response
        ];
    }

    /**
     * Normaliza resposta específica do OpenAI
     */
    private function normalizeOpenAiResponse(array $response): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => $response['usage'] ?? null,
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
            'id' => $response['id'] ?? null,
            'created' => $response['created'] ?? null,
            'raw_response' => $response
        ];
    }

    /**
     * Normaliza resposta do Google
     */
    private function normalizeGoogleResponse(array $response): array
    {
        $content = '';
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $response['candidates'][0]['content']['parts'][0]['text'];
        }

        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => $content,
            'usage' => $response['usageMetadata'] ?? null,
            'finish_reason' => $response['candidates'][0]['finishReason'] ?? null,
            'raw_response' => $response
        ];
    }

    /**
     * Normaliza resposta do Anthropic
     */
    private function normalizeAnthropicResponse(array $response): array
    {
        $content = '';
        if (isset($response['content'][0]['text'])) {
            $content = $response['content'][0]['text'];
        }

        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => $content,
            'usage' => $response['usage'] ?? null,
            'stop_reason' => $response['stop_reason'] ?? null,
            'raw_response' => $response
        ];
    }

    /**
     * Resposta padrão quando não há normalizador específico
     */
    private function getDefaultResponse(array $response): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'content' => '',
            'usage' => null,
            'raw_response' => $response
        ];
    }

    /**
     * Lista todos os provedores disponíveis
     * 
     * @return array Lista de provedores e seus modelos
     */
    public static function getAvailableProviders(): array
    {
        return array_map(function ($provider) {
            return [
                'name' => $provider,
                'models' => AiModel::provider($provider)
            ];
        }, array_keys(config('services.ia')));
    }

    /**
     * Lista modelos disponíveis para um provedor específico
     * 
     * @param string $provider Nome do provedor
     * @return array Lista de modelos
     */
    public static function getProviderModels(string $provider): array
    {
        $models = AiModel::provider($provider);
        if (empty($models)) {
            throw new InvalidArgumentException("Provedor '$provider' não encontrado ou não possui modelos ativos");
        }

        return $models;
    }

    /**
     * Limpa todas as mensagens
     */
    public function clearMessages(): self
    {
        $this->messages = [];
        return $this;
    }

    /**
     * Retorna as mensagens atuais
     * 
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Retorna o provedor atual
     * 
     * @return string
     */
    public function getCurrentProvider(): string
    {
        return $this->provider;
    }

    /**
     * Retorna o modelo atual
     * 
     * @return string
     */
    public function getCurrentModel(): string
    {
        return $this->model;
    }

    /**
     * Converte um arquivo em base64, aceita base64 diretamente ou retorna null se inválido.
     */
    private function getBinary(string $filePathOrBinary, bool $formatOnly = false): ?string
    {
        $file = getFileMetadata($filePathOrBinary);
        if ($file['exists']) {
            if ($formatOnly) {
                return pathinfo($filePathOrBinary, PATHINFO_EXTENSION);
            }
            // CORREÇÃO CRÍTICA: Retorna em base64 para o json_encode não falhar
            return base64_encode($file['handler']());
        }

        // Verifica se já é um base64 válido
        if (preg_match('/^[a-zA-Z0-9+\/]*={0,2}$/', $filePathOrBinary) && base64_decode($filePathOrBinary, true) !== false) {
            return $formatOnly ? '' : $filePathOrBinary;
        } else {
            $decoded = base64_decode($filePathOrBinary, true);
            if ($decoded !== false) {
                return $formatOnly ? '' : base64_encode($decoded);
            }
        }

        return null;
    }

    /**
     * Garante que o arquivo retorne como uma URL válida ou um Data URI formatado em Base64
     */
    private function getDataUri(string $fileContent, string $defaultMime): ?string
    {
        if (filter_var($fileContent, FILTER_VALIDATE_URL)) {
            return $fileContent;
        }

        if (strpos($fileContent, 'data:') === 0) {
            return $fileContent;
        }

        // Otimização: Usa o mime_type real fornecido pelo seu helper!
        $fileData = getFileMetadata($fileContent);
        $mime = $defaultMime;
        
        if ($fileData['exists'] && isset($fileData['mime_type'])) {
            $mime = $fileData['mime_type'];
        }

        $base64 = $this->getBinary($fileContent);
        if (!$base64) return null;

        return "data:{$mime};base64,{$base64}";
    }

    /**
     * Adiciona qualquer tipo de arquivo dinamicamente à conversa,
     * roteando para a modalidade correta baseado no mime_type real.
     *
     * @param string $filePathOrBinary Caminho no storage ou conteúdo
     * @param string|null $description Texto/Prompt associado ao arquivo
     */
    public function attach(string $filePathOrBinary, ?string $description = null): self
    {
        $fileData = getFileMetadata($filePathOrBinary);

        if ($fileData['exists']) {
            $mimeType = $fileData['mime_type'] ?? '';

            // 1. TRATAMENTO PARA IMAGENS (PNG, JPG, WEBP, GIF, etc)
            if (str_starts_with($mimeType, 'image/')) {
                return $this->image($filePathOrBinary, $description);
            }

            // 2. TRATAMENTO PARA ÁUDIOS (WAV, MP3, OGG, etc)
            if (str_starts_with($mimeType, 'audio/')) {
                return $this->audio($filePathOrBinary, $description);
            }

            // 3. TRATAMENTO PARA TEXTO PURO (TXT, CSV, JSON, XML, HTML)
            if (str_starts_with($mimeType, 'text/') || in_array($mimeType, ['application/json', 'application/xml'])) {
                $textContent = $fileData['handler'](); // Lê o texto puro do arquivo
                $promptUnificado = ($description ? $description . "\n\n" : "") . "--- CONTEÚDO DO ARQUIVO ---\n" . $textContent;
                return $this->user($promptUnificado);
            }

            // 4. TRATAMENTO PARA ARQUIVOS PDF
            if ($mimeType === 'application/pdf') {
                // Endpoints de Chat Completions (OpenAI/OpenRouter) não aceitam binário de PDF puro no payload comum.
                // O ideal é extrair o texto dele. Se o seu handler já traz o texto pré-extraído do PDF, use direto:
                $tempUrl = getFileMetadata($filePathOrBinary, '30 minutes')['handler']();
                $textContent = DocMix::downloadContents($tempUrl);
                if (is_null($textContent)){
                    $textContent = $fileData['handler'](); 
                    $textContent = (new \Smalot\PdfParser\Parser())->parseContent($fileData['handler']())->getText();
                }else{
                    $textContent = DocMix::getText($textContent);
                }

                $promptUnificado = ($description ? $description . "\n\n" : "") . "--- TEXTO EXTRAÍDO DO PDF ---\n" . $textContent;
                return $this->user($promptUnificado);
            }
        }

        // Fallback: Se o arquivo não existir ou não for mapeado, envia apenas o prompt de texto
        if ($description) {
            return $this->user($description);
        }

        return $this;
    }
}
