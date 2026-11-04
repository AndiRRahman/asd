<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
class Voucher extends Model
{
    use Audited;

    protected function casts(): array
    {
        return ['scope' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }
}
