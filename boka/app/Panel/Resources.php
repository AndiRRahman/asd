<?php

namespace App\Panel;

use App\Panel\Resources\BannerResource;
use App\Panel\Resources\CategoryResource;
use App\Panel\Resources\OriginResource;
use App\Panel\Resources\ProducerResource;

final class Resources
{
    /** @return array<string, class-string<\App\Panel\Resource>> */
    public static function all(): array
    {
        return collect([CategoryResource::class, ProducerResource::class, OriginResource::class, BannerResource::class])
            ->mapWithKeys(fn ($r) => [$r::slug() => $r])->all();
    }
}
