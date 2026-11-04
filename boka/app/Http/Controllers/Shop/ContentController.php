<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class ContentController extends Controller
{
    public function posts(): Response
    {
        return Inertia::render('shop/posts/index', [
            'posts' => Post::query()->published()->get()->map(fn ($p) => HomeController::postCard($p)),
        ]);
    }

    public function post(string $slug): Response
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        return Inertia::render('shop/posts/show', [
            'post' => [...HomeController::postCard($post), 'body' => self::clean($post->body), 'gallery' => $post->gallery ?? []],
        ]);
    }

    public function page(string $slug): Response
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        return Inertia::render('shop/page', ['page' => ['title' => $page->title, 'body' => self::clean($page->body), 'slug' => $page->slug]]);
    }

    /** Halaman publik "Rekening resmi" (11.1 T5). */
    public function officialAccounts(): Response
    {
        return Inertia::render('shop/official-accounts', [
            'accounts' => BankAccount::query()->usable()->get(['bank_name', 'account_number', 'account_holder']),
        ]);
    }

    /** Verifikasi invoice publik tanpa data pribadi (11.1 T14). */
    public function verifyInvoice(string $code): Response
    {
        $order = Order::query()->where('code', $code)->first();

        return Inertia::render('shop/invoice-verify', [
            'code' => $code,
            'order' => $order ? [
                'code' => $order->code,
                'grand_total' => $order->grand_total,
                'status' => $order->status->value,
                'paid' => in_array($order->status, [OrderStatus::Diproses, OrderStatus::Dikirim, OrderStatus::Selesai], true),
                'created_at' => $order->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    /** Isi berita disaring HTMLPurifier sebelum dirender sebagai HTML (bagian 11). */
    public static function clean(?string $html): string
    {
        return Purifier::clean((string) $html, ['HTML.Allowed' => 'p,br,strong,em,ul,ol,li,h2,h3,blockquote,a[href]', 'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true]]);
    }
}
