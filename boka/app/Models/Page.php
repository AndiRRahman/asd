<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Unguarded]
class Page extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body'];
}
