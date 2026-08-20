<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AiQueryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQuery extends Model
{
    /** @use HasFactory<AiQueryFactory> */
    use HasFactory;

    protected $fillable = [
        'provider',
        'model',
        'input',
        'response',
        'usage',
        'status',
        'error_message',
        'church_id',
        'type',
        'started_at_filter',
        'finished_at_filter',
    ];

    protected $attributes = [
        'status' => 'completed',
    ];

    // `usage` é coluna JSON e acessores (ex.: getTotalTokensAttribute) já a tratam
    // como array; sem este cast, gravar um array gera "Array to string conversion".
    protected $casts = [
        'usage' => 'array',
    ];

    /**
     * Relacionamento com empresa/company
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Scopes para filtrar por provedor
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope para filtrar por modelo
     */
    public function scopeByModel($query, string $model)
    {
        return $query->where('model', $model);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeByChurch($query, int $churchId)
    {
        return $query->where('church_id', $churchId);
    }

    /**
     * Scope para queries completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para queries com erro
     */
    public function scopeWithError($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Scope para queries recentes
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Método para calcular tokens usados
     */
    public function getTotalTokensAttribute(): ?int
    {
        return $this->usage['total_tokens'] ?? null;
    }

    /**
     * Método para obter tokens de prompt
     */
    public function getPromptTokensAttribute(): ?int
    {
        return $this->usage['prompt_tokens'] ?? null;
    }

    /**
     * Método para obter tokens de completion
     */
    public function getCompletionTokensAttribute(): ?int
    {
        return $this->usage['completion_tokens'] ?? null;
    }

    /**
     * Método para verificar se a query foi bem-sucedida
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && ! empty($this->response);
    }

    /**
     * Método para verificar se houve erro
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Método para obter o tempo de resposta formatado
     */
    public function getFormattedResponseTime(): string
    {
        if (! $this->response_time_ms) {
            return 'N/A';
        }

        if ($this->response_time_ms < 1000) {
            return $this->response_time_ms.'ms';
        }

        return round($this->response_time_ms / 1000, 2).'s';
    }

    /**
     * Método estático para criar uma nova query
     */
    public static function createFromAiProvider(array $request, array $response, ?int $responseTimeMs = null, ?int $churchId = null, ?string $type = null): self
    {
        return self::create([
            'provider' => $response['provider'] ?? 'unknown',
            'model' => $response['model'] ?? 'unknown',
            'prompt' => $request['last_user_message'] ?? '',
            'response' => $response['content'] ?? '',
            'messages' => $request['messages'] ?? [],
            'usage' => $response['usage'] ?? null,
            'finish_reason' => $response['finish_reason'] ?? null,
            'query_timestamp' => $response['created'] ? Carbon::createFromTimestamp($response['created']) : now(),
            'response_time_ms' => $responseTimeMs,
            'raw_response' => $response['raw_response'] ?? $response,
            'status' => empty($response['content']) ? 'error' : 'completed',
            'church_id' => $churchId,
            'type' => $type,
            'metadata' => $request['metadata'] ?? null,
        ]);
    }

    /**
     * Método para obter estatísticas de uso
     */
    public static function getUsageStats(int $days = 30, ?int $churchId = null): array
    {
        $baseQuery = self::recent($days);

        if ($churchId) {
            $baseQuery = $baseQuery->byChurch($churchId);
        }

        return [
            'total_queries' => $baseQuery->count(),
            'successful_queries' => $baseQuery->completed()->count(),
            'error_queries' => $baseQuery->withError()->count(),
            'total_tokens' => $baseQuery->sum('usage->total_tokens'),
            'avg_response_time' => $baseQuery->avg('response_time_ms'),
            'providers_usage' => $baseQuery->selectRaw('provider, COUNT(*) as count')
                ->groupBy('provider')
                ->pluck('count', 'provider')
                ->toArray(),
            'models_usage' => $baseQuery->selectRaw('model, COUNT(*) as count')
                ->groupBy('model')
                ->pluck('count', 'model')
                ->toArray(),
            'types_usage' => $baseQuery->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}
