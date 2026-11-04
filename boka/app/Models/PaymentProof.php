<?php

namespace App\Models;

use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class PaymentProof extends Model
{
    use HasHashId;

    protected function casts(): array
    {
        return ['transferred_at' => 'date', 'reviewed_at' => 'datetime', 'mutation_datetime' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
