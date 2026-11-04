<?php

namespace App\Models;

use App\Models\Concerns\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Unguarded]
class ProductImage extends Model
{
    use HasHashId;

    protected function casts(): array
    {
        return ['variants' => 'array', 'is_cover' => 'boolean'];
    }

    public function url(): string
    {
        return Str::startsWith($this->path, ['http', '/']) ? $this->path : asset('storage/'.$this->path);
    }

    public function toFront(): array
    {
        return [
            'url' => $this->url(),
            'width' => $this->width ?: 800,
            'height' => $this->height ?: 800,
            'color' => $this->dominant_color,
        ];
    }
}
