<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Token per perangkat, dicabut saat logout atau ganti password.
 */
class AuthController extends Controller
{
    public function register(Request $request, CreateNewUser $creator): JsonResponse
    {
        $user = $creator->create($request->all());
        $user->sendEmailVerificationNotification();

        return response()->json([
            'token' => $user->createToken($request->input('device_name', 'mobile'))->plainTextToken,
            'user' => self::user($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['required', 'string', 'max:100']]);
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || $user->status !== 'aktif') {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['token' => $user->createToken($data['device_name'])->plainTextToken, 'user' => self::user($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Keluar.']);
    }

    public function resend(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => __('Tautan verifikasi dikirim.')]);
    }

    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Jawaban sama untuk email terdaftar maupun tidak, agar tidak membocorkan daftar akun.
        return response()->json(['message' => 'Jika email terdaftar, tautan reset sudah dikirim.']);
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->hashid,
            ...$user->only(['name', 'email', 'phone', 'locale', 'theme']),
            'email_verified' => $user->hasVerifiedEmail(),
            'checkout_blocked_until' => $user->checkout_blocked_until?->toIso8601String(),
        ];
    }
}
