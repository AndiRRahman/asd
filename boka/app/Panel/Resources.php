<?php

namespace App\Panel;

use App\Panel\Resources\BannerResource;
use App\Panel\Resources\CategoryResource;
use App\Panel\Resources\FlashSaleResource;
use App\Panel\Resources\OriginResource;
use App\Panel\Resources\PageResource;
use App\Panel\Resources\PostResource;
use App\Panel\Resources\ProducerResource;
use App\Panel\Resources\VoucherResource;

final class Resources
{
    /** @return array<string, class-string<\App\Panel\Resource>> */
    public static function all(): array
    {
        return collect([CategoryResource::class, ProducerResource::class, OriginResource::class, VoucherResource::class, FlashSaleResource::class, BannerResource::class, PostResource::class, PageResource::class])
            ->mapWithKeys(fn ($r) => [$r::slug() => $r])->all();
    }
}
