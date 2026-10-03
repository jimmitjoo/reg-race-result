<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['until' => 'immutable_date'];
    }
}
