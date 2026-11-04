<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

#[Unguarded]
class Producer extends Model
{
    use Audited, HasTranslations;

    public array $translatable = ['story'];

    public function defaultOrigin(): BelongsTo
    {
        return $this->belongsTo(Origin::class, 'default_origin_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'karang_taruna' ? 'Karang Taruna' : 'UMKM';
    }
}
