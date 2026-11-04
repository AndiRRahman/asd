<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class VoucherUsage extends Model
{
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
