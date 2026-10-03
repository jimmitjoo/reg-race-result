<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birth_date' => 'immutable_date', 'paid_at' => 'immutable_datetime', 'manual_finish_at' => UtcDateTime::class.':ms'];
    }

    public function markPaid(): void
    {
        if (! $this->paid_at) {
            $this->update(['paid_at' => now()]);
        }
    }

    public function raceClass(): BelongsTo
    {
        return $this->belongsTo(RaceClass::class);
    }
}
