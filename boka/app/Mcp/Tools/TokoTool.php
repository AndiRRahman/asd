<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Dasar semua tool toko: memeriksa izin yang sama dengan panel web dan mencatat setiap panggilan
 * ke log audit (nama tool, argumen, pengguna). Admin tidak mendapat hak lebih lewat MCP.
 */
abstract class TokoTool extends Tool
{
    protected string $permission = 'panel.akses';

    abstract protected function run(Request $request, User $user): Response|ResponseFactory;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can($this->permission) || $user->status !== 'aktif') {
            return Response::error("Akses ditolak: token ini tidak punya izin '{$this->permission}'. Buat token dari akun Admin yang berwenang.");
        }

        activity()->causedBy($user)->withProperties(['tool' => $this->name(), 'arguments' => $request->all()])->log('Panggilan tool MCP');

        return $this->run($request, $user);
    }

    /** Tanggal masukan dibaca dalam WITA; bawaan 30 hari terakhir. */
    protected function period(Request $request): array
    {
        $from = Carbon::parse($request->get('from') ?? now('Asia/Makassar')->subDays(29)->toDateString(), 'Asia/Makassar')->startOfDay();
        $to = Carbon::parse($request->get('to') ?? now('Asia/Makassar')->toDateString(), 'Asia/Makassar')->endOfDay();

        return [$from->utc(), $to->utc()];
    }

    protected static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
