<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class Cart extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
