<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What someone sent with a form, kept as label and value so that a later change to the form leaves it readable. */
class FormResponse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['answers' => 'array'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
