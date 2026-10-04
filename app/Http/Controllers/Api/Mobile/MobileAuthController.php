<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\ApiController;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Authentification de l'application mobile du convoyeur : connexion, déconnexion, changement de mot de passe. */
class MobileAuthController extends ApiController
{
    public const ABILITY = 'mobile';

    public function login(Request $request)
    {
        $data = $request->validate([
            'identifiant' => ['required_without:email', 'nullable', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'max:160'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        // L'identifiant (« kone.ibrahim ») est généré avec la fiche personnel ; une adresse e-mail reste acceptée.
        $login = Str::lower(trim((string) ($data['identifiant'] ?? $data['email'])));
        $user = str_contains($login, '@') ? User::where('email', $login)->first() : Personnel::where('identifiant', $login)->first()?->user;
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active || ! $user->can('execute_circuits') || ! $user->personnel_id) {
            // Même message dans tous les cas : on ne révèle pas si le compte existe.
            throw ValidationException::withMessages(['identifiant' => ['Identifiant ou code d\'accès invalide.']]);
        }

        $user->forceFill(['last_mobile_login_at' => now()])->save();

        return [
            'token' => $user->createToken($data['device_name'] ?? 'mobile', [self::ABILITY], now()->addDays(30))->plainTextToken,
            'must_change_password' => (bool) $user->must_change_password,
            'user' => $this->profile($user),
        ];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        // Les téléphones se désabonnent explicitement des notifications avant (DELETE push-subscriptions) ; on retire aussi celui-ci.
        if ($endpoint = $request->input('endpoint')) {
            $request->user()->pushSubscriptions()->where('endpoint_hash', \App\Models\PushSubscription::hashEndpoint((string) $endpoint))->delete();
        }

        return response()->json(null, 204);
    }

    public function me(Request $request)
    {
        return ['must_change_password' => (bool) $request->user()->must_change_password, 'user' => $this->profile($request->user())];
    }

    /** Change le mot de passe (obligatoire après un mot de passe provisoire) ; les autres appareils sont déconnectés. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Choisissez un mot de passe différent de l\'actuel.']]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->personnel?->forceFill(['code_acces' => null])->saveQuietly();   // le code provisoire n'est plus affiché au bureau
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return ['must_change_password' => false];
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        $user->loadMissing('personnel.district');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'identifiant' => $user->personnel?->identifiant,
            'fonction' => $user->personnel?->fonction,
            'fonction_label' => \App\Models\Personnel::FONCTIONS[$user->personnel?->fonction] ?? null,
            'district' => $user->personnel?->district?->name,
        ];
    }
}
