<?php

namespace App\Models;

use App\Domain\Catalog\ProductStatus;
use App\Models\Concerns\Audited;
use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Translatable\HasTranslations;

#[Unguarded]
class Product extends Model
{
    use Audited, HasHashId, HasTranslations, Searchable, SoftDeletes;

    public array $translatable = ['name', 'description'];

    /** Indeks Meilisearch memuat semua bahasa, jadi "coffee" tetap menemukan "Kopi Robusta Bone". */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => implode(' ', $this->getTranslations('name')),
            'name_normalized' => $this->name_normalized,
            'producer' => $this->producer?->name,
            'tags' => $this->tags->pluck('name')->implode(' '),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ProductStatus::Aktif;
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'similarity_resolved' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ProductStatus::Aktif);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('price');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_cover')->orderBy('sort_order');
    }

    public function cover(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderByDesc('is_cover')->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
