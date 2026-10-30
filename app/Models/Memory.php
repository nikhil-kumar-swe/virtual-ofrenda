<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Memory extends Model
{
    protected $fillable = ['name', 'text', 'source'];

    public function tribute(): HasOne
    {
        return $this->hasOne(Tribute::class);
    }
}