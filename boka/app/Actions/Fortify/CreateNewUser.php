<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Registrasi Pembeli: nama, email, nomor WhatsApp, password, dan persetujuan kebijakan privasi (bagian 3.2).
     * Satu nomor WhatsApp hanya untuk satu akun (11.1 T12).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = isset($input['phone']) ? preg_replace('/[\s-]/', '', $input['phone']) : null;

        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,12}$/', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'privacy' => ['accepted'],
            // Honeypot: kolom tersembunyi yang hanya diisi bot.
            'website' => ['prohibited'],
        ], [
            'phone.regex' => 'Nomor WhatsApp harus diawali 08 atau +628.',
            'phone.unique' => 'Nomor WhatsApp ini sudah dipakai akun lain.',
            'privacy.accepted' => 'Setujui kebijakan privasi untuk mendaftar.',
            'website.prohibited' => 'Pendaftaran ditolak.',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
            'locale' => app()->getLocale(),
            'privacy_accepted_at' => now(),
        ]);

        $user->assignRole('pembeli');

        return $user;
    }
}
