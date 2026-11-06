<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('admin:password {email=admin@boka.test} {password=password}')]
#[Description('Set atau reset password akun admin di server')]
class ResetAdminPassword extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'email_verified_at' => now()]
        );

        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        if (!$user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $this->info("Berhasil! Akun: {$email}");
        $this->info("Password baru: {$password}");
        $this->info("Role: super_admin (Akses Panel Aktif)");

        return self::SUCCESS;
    }
}
