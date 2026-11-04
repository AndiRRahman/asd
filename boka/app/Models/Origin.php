<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class Origin extends Model
{
    use Audited, HasHashId;

    protected function casts(): array
    {
        return ['couriers' => 'array', 'pickup_enabled' => 'boolean', 'is_active' => 'boolean'];
    }

    public function localRates(): HasMany
    {
        return $this->hasMany(LocalCourierRate::class);
    }

    public function snapshot(): array
    {
        return $this->only(['id', 'name', 'address', 'district', 'contact', 'pickup_enabled', 'pickup_hours']);
    }
}
