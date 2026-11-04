<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hapus akun = anonimkan identitas. Pesanan tetap tersimpan sebagai catatan keuangan (UU PDP, bagian 11).
 */
class AnonymizeUser
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->addresses()->delete();
            $user->wishlists()->delete();
            $user->cart?->items()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('search_logs')->where('user_id', $user->id)->update(['user_id' => null]);

            $user->forceFill([
                'name' => 'Pengguna terhapus',
                'email' => 'terhapus-'.$user->id.'-'.Str::lower(Str::random(8)).'@anon.boka',
                'phone' => null,
                'status' => 'nonaktif',
                'password' => Str::random(48),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'remember_token' => null,
            ])->save();
        });
    }
}
