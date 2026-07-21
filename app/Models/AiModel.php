<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'provider', 
        'model_id', 
        'name', 
        'status', // 'active', 'inactive', 'removed'
        'position',
        'context_length',
        'input_modalities',
        'output_modalities',
        'price_prompt',
        'price_completion'
    ];

    protected $casts = [
        'context_length' => 'integer',
        'position' => 'integer',
        'price_prompt' => 'float',
        'price_completion' => 'float',
    ];

    protected $appends = ['is_active', 'is_free', 'input_modalities_array', 'output_modalities_array'];

    /**
     * Boot do modelo para sempre ordenar por Provedor e depois pela Posição definida pelo utilizador.
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order_provider_position', function (Builder $builder) {
            $builder->orderBy('provider', 'asc')
                    ->orderBy('position', 'asc')
                    ->orderBy('id', 'asc');
        });
    }

    /**
     * Acessores para manter compatibilidade com o atributo virtual 'is_active'
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsFreeAttribute(): bool
    {
        return ($this->price_prompt == 0 && $this->price_completion == 0);
    }

    public function getInputModalitiesArrayAttribute(): array
    {
        return $this->input_modalities ? explode(',', $this->input_modalities) : [];
    }

    public function getOutputModalitiesArrayAttribute(): array
    {
        return $this->output_modalities ? explode(',', $this->output_modalities) : [];
    }

    public static function provider(string $provider)
    {
        return self::where('provider', $provider)->where('status', 'active')->orderBy('position')->pluck('model_id')->toArray();
    }
}
