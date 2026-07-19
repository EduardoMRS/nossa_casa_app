<?php

namespace App\Models;

// Altere a importação da classe mãe:
use Illuminate\Database\Eloquent\Relations\Pivot; 
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class EventConfirmation extends Pivot {
    use HasUlids;

    // Como Pivot não usa $fillable por padrão, você pode remover ou manter
    // O incremento automático precisa ser falso para o ULID funcionar no Pivot
    public $incrementing = false; 
    
    protected $table = 'event_confirmations';

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
