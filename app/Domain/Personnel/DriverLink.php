<?php

namespace App\Domain\Personnel;

use App\Models\Driver;
use App\Models\Personnel;
use Illuminate\Support\Str;

/**
 * Lien personne ↔ fiche chauffeur. La liste « Personnel » est la seule à éditer ; la fiche chauffeur (à laquelle
 * sorties, chronogramme, pleins et alertes de permis sont rattachés) suit la personne. Un chauffeur créé ailleurs
 * (import des classeurs, API) reçoit automatiquement sa fiche dans le personnel.
 */
class DriverLink
{
    /** Évite les allers-retours personne → chauffeur → personne pendant une synchronisation. */
    private static bool $syncing = false;

    public const DRIVER_FIELDS = ['matricule', 'categorie_permis', 'numero_permis', 'permis_expiration', 'date_obtention_permis', 'vehicule_principal_id'];

    /** Personne enregistrée : si elle est chauffeur, sa fiche chauffeur est créée ou mise à jour ; sinon elle est désactivée. */
    public static function fromPersonnel(Personnel $p): void
    {
        if (self::$syncing) {
            return;
        }
        $driver = $p->driver_id ? Driver::withTrashed()->find($p->driver_id) : null;
        if ($p->fonction !== 'chauffeur') {
            if (! $driver) {
                return;
            }
            // n'est plus chauffeur : sa fiche chauffeur est désactivée (historique des sorties conservé)
            if ($p->wasChanged('fonction') && $p->getOriginal('fonction') === 'chauffeur') {
                $driver->statut !== 'inactif' && self::quietly(fn () => $driver->forceFill(['statut' => 'inactif'])->save());

                return;
            }
            // chef de mission qui conduit aussi (fiche chauffeur à son nom) : la fiche reste à jour, sans changer son statut
            self::quietly(fn () => $driver->forceFill(['nom_complet' => $p->nom_complet, 'telephone' => $p->telephone, 'email' => $p->email] + ($p->statut === 'inactif' ? ['statut' => 'inactif'] : []))->save());

            return;
        }

        $driver ??= new Driver;
        $driver->fill([
            'district_id' => $p->district_id, 'nom_complet' => $p->nom_complet, 'telephone' => $p->telephone, 'email' => $p->email,
            'statut' => $p->statut === 'inactif' ? 'inactif' : ($driver->exists && $driver->statut !== 'inactif' ? $driver->statut : 'actif'),
            'matricule' => $p->matricule ?: ($driver->matricule ?: self::newMatricule()),
        ] + collect(self::DRIVER_FIELDS)->except(0)->mapWithKeys(fn ($f) => [$f => $p->{$f}])->all());
        $driver->trashed() && $driver->restore();

        self::quietly(function () use ($driver, $p) {
            $driver->save();
            if ($p->driver_id !== $driver->id || $p->matricule !== $driver->matricule) {
                $p->forceFill(['driver_id' => $driver->id, 'matricule' => $driver->matricule])->saveQuietly();
            }
        });
    }

    /** Fiche chauffeur créée ou modifiée hors du personnel (import, API) : la personne correspondante est créée ou mise à jour. */
    public static function fromDriver(Driver $d): void
    {
        if (self::$syncing || $d->trashed()) {
            return;
        }
        $p = Personnel::where('driver_id', $d->id)->first()
            ?? Personnel::where('district_id', $d->district_id)->whereNull('driver_id')->get()->first(fn ($x) => self::norm($x->nom_complet) === self::norm($d->nom_complet))
            ?? new Personnel(['fonction' => 'chauffeur', 'statut' => 'actif']);

        $p->fill([
            'district_id' => $d->district_id, 'nom_complet' => $d->nom_complet,
            'telephone' => $d->telephone ?? $p->telephone, 'email' => $d->email ?? $p->email,
        ]);
        $p->forceFill(collect(self::DRIVER_FIELDS)->mapWithKeys(fn ($f) => [$f => $d->{$f}])->all() + ['driver_id' => $d->id]);
        if (in_array($p->fonction, [null, 'autre'], true)) {
            $p->fonction = 'chauffeur';
        }
        if ($d->statut === 'inactif') {
            $p->statut = 'inactif';
        }
        // la personne est enregistrée avec ses propres hooks (accès mobile), sans revenir vers la fiche chauffeur
        self::quietly(fn () => $p->save());
    }

    private static function quietly(callable $fn): void
    {
        self::$syncing = true;
        try {
            $fn();
        } finally {
            self::$syncing = false;
        }
    }

    private static function norm(?string $s): string
    {
        return Str::of((string) $s)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->trim()->toString();
    }

    /** Matricule interne pour un chauffeur saisi sans matricule (unique, modifiable ensuite). */
    private static function newMatricule(): string
    {
        do {
            $m = 'CH-'.strtoupper(Str::random(6));
        } while (Driver::withTrashed()->where('matricule', $m)->exists());

        return $m;
    }
}
