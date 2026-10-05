<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordPressSite extends Model
{
    protected $table = 'wordpress_sites';

    protected $guarded = [];

    protected $hidden = ['app_password'];

    protected function casts(): array
    {
        return ['app_password' => 'encrypted'];
    }

    public function api(string $path): string
    {
        return $this->url.'/wp-json/wp/v2/'.ltrim($path, '/');
    }
}
