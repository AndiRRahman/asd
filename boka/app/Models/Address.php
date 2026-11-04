<?php

namespace App\Models;

use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class Address extends Model
{
    use HasHashId;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function snapshot(): array
    {
        return $this->only(['label', 'recipient', 'phone', 'destination_id', 'district', 'destination_label', 'full_address', 'postal_code']);
    }
}
