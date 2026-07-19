<?php

namespace App\Models;

// Altere a importação da classe mãe:
use Illuminate\Database\Eloquent\Relations\Pivot; 
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class EventUser extends Pivot {
    use HasUlids;

    // Como Pivot não usa $fillable por padrão, você pode remover ou manter
    // O incremento automático precisa ser falso para o ULID funcionar no Pivot
    public $incrementing = false; 
    
    protected $table = 'event_users';

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function confirm(User $user)
    {
        if ($this->user_id === $user->id) {
            $this->status = 'confirmed';
            $this->save();
        } else {
            throw new \Exception("User ID does not match the event user record.");
        }        
    }

    public function checkIn(User $user)
    {
        if ($this->user_id === $user->id) {
            $this->check_in_at = now();
            $this->save();
        } else {
            throw new \Exception("User ID does not match the event user record.");
        }        
    }

    public function checkOut(User $user)
    {
        if ($this->user_id === $user->id) {
            $this->check_out_at = now();
            $this->save();
        } else {
            throw new \Exception("User ID does not match the event user record.");
        }        
    }
}
