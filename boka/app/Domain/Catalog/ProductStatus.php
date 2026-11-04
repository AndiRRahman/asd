<?php

namespace App\Domain\Catalog;

enum ProductStatus: string
{
    case Draf = 'draf';
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';
    case Arsip = 'arsip';
}
