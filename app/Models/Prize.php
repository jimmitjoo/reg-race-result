<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prize extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }
}
