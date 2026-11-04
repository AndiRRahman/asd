<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Unguarded]
class Post extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'excerpt', 'body'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'gallery' => 'array'];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at');
    }
}
