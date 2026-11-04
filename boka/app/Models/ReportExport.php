<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class ReportExport extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array', 'contains_personal_data' => 'boolean', 'expires_at' => 'datetime'];
    }
}
