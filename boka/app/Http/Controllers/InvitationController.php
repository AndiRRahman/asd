<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin menerima undangan dan mengatur password sendiri; password tidak pernah dikirim lewat email.
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, AdminInvitation $invitation): Response
    {
        $this->guard($request, $invitation);

        return Inertia::render('auth/accept-invitation', ['email' => $invitation->email, 'role' => $invitation->role, 'action' => $request->fullUrl()]);
    }

    public function accept(Request $request, AdminInvitation $invitation): RedirectResponse
    {
        $this->guard($request, $invitation);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'password' => $this->passwordRules()]);

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::query()->create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($invitation->role);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('security.edit');
    }

    private function guard(Request $request, AdminInvitation $invitation): void
    {
        abort_unless(
            $request->hasValidSignature()
            && $invitation->accepted_at === null
            && $invitation->expires_at->isFuture()
            && hash_equals($invitation->token_hash, hash('sha256', (string) $request->query('token'))),
            403,
            'Tautan undangan tidak berlaku atau sudah dipakai.',
        );
    }
}
