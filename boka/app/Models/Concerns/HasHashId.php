<?php

namespace App\Models\Concerns;

use App\Support\HashId;
use Illuminate\Database\Eloquent\Model;

/**
 * Route model binding memakai HashID: URL berisi kode acak, bukan ID angka berurutan.
 */
trait HasHashId
{
    public function getRouteKey(): string
    {
        return HashId::encode((int) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $id = HashId::decode($value);

        return $id === null ? null : $this->newQuery()->whereKey($id)->first();
    }

    public function getHashidAttribute(): string
    {
        return $this->getRouteKey();
    }
}
