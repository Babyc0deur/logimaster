<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Facture fournisseur et son workflow : brouillon → à valider → validée → payée → archivée
 * (ou rejetée, puis re-soumise). Chaque étape est tracée dans `historique`.
 */
class Facture extends Model
{
    use HasUuids;

    public const STATUTS = [
        'brouillon' => 'Brouillon', 'a_valider' => 'À valider', 'validee' => 'Validée',
        'rejetee' => 'Rejetée', 'payee' => 'Payée', 'archivee' => 'Archivée',
    ];

    public const CATEGORIES = ['carburant' => 'Carburant', 'maintenance' => 'Maintenance', 'autres' => 'Autres frais'];

    public const MODES_PAIEMENT = ['virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'mobile_money' => 'Mobile money'];

    protected $fillable = [
        'district_id', 'numero', 'fournisseur', 'date_facture', 'categorie', 'montant', 'vehicle_id', 'bailleur', 'description',
        'fichier_path', 'statut', 'cree_par', 'soumise_at', 'valide_par', 'valide_at', 'motif_rejet', 'paye_par', 'paye_at',
        'mode_paiement', 'reference_paiement', 'archive_at', 'historique',
    ];

    protected function casts(): array
    {
        return [
            'date_facture' => 'date:Y-m-d', 'montant' => 'decimal:2', 'historique' => 'array',
            'soumise_at' => 'datetime', 'valide_at' => 'datetime', 'paye_at' => 'datetime', 'archive_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (Facture $f) => $f->trace('creation', $f->createur));
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createur()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    // ------------------------------------------------------------------ workflow

    public function soumettre(User $by): self
    {
        abort_unless(in_array($this->statut, ['brouillon', 'rejetee'], true), 422, 'Seule une facture en brouillon ou rejetée peut être soumise.');
        $this->forceFill(['statut' => 'a_valider', 'soumise_at' => now(), 'motif_rejet' => null])->save();
        $this->trace('soumission', $by);

        // « Facture à approuver » : notifie ceux qui peuvent la valider dans ce district.
        User::permission('validate_factures')->get()
            ->filter(fn (User $u) => $u->is_active && $u->getKey() !== $by->getKey() && $u->canAccessDistrict($this->district_id))
            ->each(fn (User $u) => Notification::make()->title('Facture à approuver')
                ->body("{$this->fournisseur} — n° {$this->numero} : ".number_format((float) $this->montant, 0, ',', ' ').' FCFA')
                ->warning()->sendToDatabase($u));

        return $this;
    }

    public function valider(User $by): self
    {
        abort_unless($this->statut === 'a_valider', 422, 'Seule une facture « à valider » peut être validée.');
        abort_if($this->cree_par === $by->getKey() && ! $by->isNational(), 403, 'Vous ne pouvez pas valider une facture que vous avez saisie.');
        $this->forceFill(['statut' => 'validee', 'valide_par' => $by->getKey(), 'valide_at' => now()])->save();

        return $this->trace('validation', $by);
    }

    public function rejeter(User $by, string $motif): self
    {
        abort_unless($this->statut === 'a_valider', 422, 'Seule une facture « à valider » peut être rejetée.');
        abort_if(trim($motif) === '', 422, 'Le motif du rejet est obligatoire.');
        $this->forceFill(['statut' => 'rejetee', 'motif_rejet' => $motif, 'valide_par' => $by->getKey(), 'valide_at' => now()])->save();

        return $this->trace('rejet', $by, $motif);
    }

    public function payer(User $by, string $mode, ?string $reference = null): self
    {
        abort_unless($this->statut === 'validee', 422, 'Seule une facture validée peut être payée.');
        abort_unless(array_key_exists($mode, self::MODES_PAIEMENT), 422, 'Mode de paiement invalide.');
        $this->forceFill(['statut' => 'payee', 'paye_par' => $by->getKey(), 'paye_at' => now(), 'mode_paiement' => $mode, 'reference_paiement' => $reference])->save();

        return $this->trace('paiement', $by, trim("{$mode} {$reference}"));
    }

    public function archiver(User $by): self
    {
        abort_unless($this->statut === 'payee', 422, 'Seule une facture payée peut être archivée.');
        $this->forceFill(['statut' => 'archivee', 'archive_at' => now()])->save();

        return $this->trace('archivage', $by);
    }

    /** Une facture soumise, validée, payée ou archivée n'est plus modifiable. */
    public function isEditable(): bool
    {
        return in_array($this->statut, ['brouillon', 'rejetee'], true);
    }

    public function trace(string $action, ?User $by, ?string $note = null, bool $save = true): self
    {
        $this->historique = [...($this->historique ?? []), [
            'action' => $action, 'par' => $by?->name, 'par_id' => $by?->getKey(), 'le' => now()->toIso8601String(), 'note' => $note,
        ]];
        $save && $this->save();

        return $this;
    }
}
