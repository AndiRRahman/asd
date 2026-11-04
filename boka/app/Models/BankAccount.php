<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class BankAccount extends Model
{
    use Audited, HasHashId;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'active_from' => 'datetime'];
    }

    /** Rekening aktif yang sudah melewati masa tunggu 24 jam (11.1 T4). */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->where('is_active', true)->where('active_from', '<=', now());
    }

    public function snapshot(): array
    {
        return $this->only(['id', 'bank_name', 'account_number', 'account_holder']);
    }
}
