<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\ApiController;
use App\Models\District;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Authentification de l'application mobile du bureau : les comptes de l'administration (administrateur national,
 * gestionnaire de district…) qui ont la permission « exécuter les circuits » se connectent avec leur adresse e-mail.
 */
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

        $login = Str::lower(trim((string) ($data['email'] ?? $data['identifiant'])));
        $user = User::where('email', $login)->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active || ! $user->can('execute_circuits')) {
            // Même message dans tous les cas : on ne révèle pas si le compte existe.
            \App\Support\AdminAudit::failedLogin($login, 'mobile', $user?->getKey());
            throw ValidationException::withMessages(['identifiant' => ['Adresse e-mail ou mot de passe invalide.']]);
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
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Mot de passe actuel incorrect.']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Choisissez un mot de passe différent de l\'actuel.']]);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return ['must_change_password' => false];
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        $ids = $user->accessibleDistrictIds();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'fonction_label' => \App\Support\PermissionCatalog::roleLabel((string) $user->roles->first()?->name),
            // districts que l'utilisateur peut choisir dans l'application (national : tous)
            'districts' => District::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values(),
        ];
    }
}
