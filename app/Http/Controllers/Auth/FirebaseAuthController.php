<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\FirebaseAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Firebase\FirebaseIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Segundo método de login: "Continuar con Google" a través de Firebase Auth.
 *
 * El navegador autentica con el SDK de Firebase, obtiene un ID token firmado y
 * lo envía aquí. El servidor lo verifica y crea/abre la sesión de Laravel.
 */
class FirebaseAuthController extends Controller
{
    public function __construct(private readonly FirebaseIdTokenVerifier $verifier) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $identity = $this->verifier->verify($validated['id_token']);
        } catch (FirebaseAuthException $exception) {
            logger()->warning('Login con Firebase rechazado', [
                'reason' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'firebase' => $exception->getMessage(),
            ]);
        }

        $user = DB::transaction(function () use ($identity): User {
            $user = User::query()
                ->where('firebase_uid', $identity->uid)
                ->first()
                ?? User::query()->where('email', $identity->email)->first()
                ?? new User;

            $user->fill([
                'name' => $user->exists ? $user->name : $identity->name,
                'email' => $identity->email,
                'firebase_uid' => $identity->uid,
                'avatar' => $identity->avatar ?? $user->avatar,
            ]);

            if ($identity->emailVerified && $user->email_verified_at === null) {
                $user->email_verified_at = now();
            }

            // Los usuarios de Google no tienen contraseña local: entran solo
            // con Firebase. Si ya existía una cuenta con email/contraseña, se
            // conserva su contraseña y simplemente se vincula la cuenta.
            $user->save();

            return $user;
        });

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('dashboard', absolute: false),
        ]);
    }
}
