<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class OrderRefund extends Model
{
    use Audited, HasHashId;

    protected function casts(): array
    {
        return ['refunded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
