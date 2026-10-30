<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tribute extends Model
{
    protected $fillable = ['memory_id', 'story', 'poem', 'items', 'status', 'error'];

    protected $casts = ['items' => 'array'];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}