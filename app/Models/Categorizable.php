<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Model;

class Categorizable extends Model
{

    protected $fillable = [
        'category_id',
        'categorizable_type',
        'categorizable_id',
    ];

    protected $table = 'categorizables';
}
