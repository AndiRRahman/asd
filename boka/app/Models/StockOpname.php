<?php

namespace App\Models;

use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class StockOpname extends Model
{
    use HasHashId;

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
