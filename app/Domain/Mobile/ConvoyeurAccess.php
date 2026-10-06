<?php

namespace App\Domain\Mobile;

use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Accès à l'application mobile : tout chauffeur, chef de mission ou passager actif est convoyeur d'office. Son compte est créé
 * automatiquement avec sa fiche (identifiant généré + code d'accès provisoire), suspendu quand il devient inactif ou change
 * de fonction, et réactivé quand il redevient éligible.
 */
class ConvoyeurAccess
{
    public const FONCTIONS = ['chauffeur', 'chef_mission', 'passager'];

    public static function enabled(): bool
    {
        return (bool) config('logimaster.mobile.auto_access', true);
    }

    public function eligible(Personnel $p): bool
    {
        return in_array($p->fonction, self::FONCTIONS, true) && $p->statut !== 'inactif';
    }

    /** Crée, met à jour ou suspend le compte mobile d'une personne selon sa fiche. Rend le compte (ou null si jamais créé). */
    public function sync(Personnel $p): ?User
    {
        $user = $p->user()->first();

        if (! $this->eligible($p)) {
            $user && $this->suspend($user);

            return $user;
        }

        $this->ensureIdentifiant($p);

        if (! $user) {
            $code = self::temporaryCode();
            // fiche supprimée puis recréée (restauration, réimport) : on reprend l'ancien compte orphelin au lieu d'en créer un second
            $user = User::where('email', $this->internalEmail($p))->first() ?? new User;
            $user->forceFill([
                'name' => $p->nom_complet, 'email' => $this->internalEmail($p), 'password' => $code,
                'is_active' => true, 'personnel_id' => $p->getKey(), 'must_change_password' => true,
            ])->save();
            \Spatie\Permission\Models\Role::findOrCreate(User::ROLE_CONVOYEUR, 'web');   // base pas encore initialisée (import, tests) : le rôle est créé, ses droits viennent du seeder
            $user->assignRole(User::ROLE_CONVOYEUR);
            $p->forceFill(['code_acces' => $code])->saveQuietly();
        } else {
            $user->forceFill(['name' => $p->nom_complet, 'is_active' => true])->save();
        }
        $user->districts()->sync([$p->district_id]);

        return $user;
    }

    /** Nouveau code d'accès provisoire : l'ancien mot de passe ne fonctionne plus et les appareils sont déconnectés. */
    public function resetCode(Personnel $p): string
    {
        $user = $this->sync($p) ?? abort(422, 'Cette personne n\'a pas d\'accès mobile (chef de mission ou passager actif requis).');
        abort_unless($this->eligible($p), 422, 'Cette personne n\'a pas d\'accès mobile (chef de mission ou passager actif requis).');

        $code = self::temporaryCode();
        $user->forceFill(['password' => $code, 'must_change_password' => true, 'is_active' => true])->save();
        $user->tokens()->delete();
        $p->forceFill(['code_acces' => $code])->saveQuietly();

        return $code;
    }

    private function suspend(User $user): void
    {
        if ($user->is_active) {
            $user->forceFill(['is_active' => false])->save();
        }
        $user->tokens()->delete();
        $user->pushSubscriptions()->delete();
    }

    /** « kone.ibrahim » (suffixe numérique en cas d'homonyme) : identifiant stable, sans accent ni espace. */
    private function ensureIdentifiant(Personnel $p): void
    {
        if ($p->identifiant) {
            return;
        }
        $base = Str::of($p->nom_complet)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->limit(40, '')->toString() ?: 'convoyeur';
        $candidate = $base;
        for ($i = 2; Personnel::where('identifiant', $candidate)->exists(); $i++) {
            $candidate = "{$base}.{$i}";
        }
        $p->forceFill(['identifiant' => $candidate])->saveQuietly();
    }

    /** Adresse technique du compte (jamais utilisée pour écrire) : la personne se connecte avec son identifiant. */
    private function internalEmail(Personnel $p): string
    {
        return $p->identifiant.'@convoyeur.logimaster.local';
    }

    /** 8 caractères faciles à saisir sur un téléphone (majuscules et chiffres, sans 0/O ni 1/I). */
    public static function temporaryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
