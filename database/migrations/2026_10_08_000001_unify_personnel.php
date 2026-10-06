<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Une seule liste « Personnel » : chauffeurs, chefs de mission et passagers. La fiche chauffeur (table drivers, à laquelle
 * sont rattachés sorties, chronogramme, pleins, alertes de permis) reste en coulisse, liée à une personne et tenue à jour
 * depuis elle. Les chauffeurs existants sont repris : rattachés à la personne du même nom dans le district, sinon ajoutés
 * au personnel avec la fonction « chauffeur ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->foreignUuid('driver_id')->nullable()->unique()->constrained('drivers')->nullOnDelete();
            $table->string('matricule', 30)->nullable();
            $table->string('categorie_permis', 10)->nullable();
            $table->string('numero_permis', 40)->nullable();
            $table->date('permis_expiration')->nullable();
            $table->date('date_obtention_permis')->nullable();
            $table->foreignUuid('vehicule_principal_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });

        $norm = fn (?string $s) => Str::of((string) $s)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->trim()->toString();
        $now = now();
        foreach (DB::table('drivers')->whereNull('deleted_at')->orderBy('created_at')->get() as $d) {
            $fields = [
                'driver_id' => $d->id, 'matricule' => $d->matricule, 'categorie_permis' => $d->categorie_permis, 'numero_permis' => $d->numero_permis ?? null,
                'permis_expiration' => $d->permis_expiration, 'date_obtention_permis' => $d->date_obtention_permis ?? null,
                'vehicule_principal_id' => $d->vehicule_principal_id ?? null, 'updated_at' => $now,
            ];
            $match = DB::table('personnels')->where('district_id', $d->district_id)->whereNull('driver_id')->get()
                ->first(fn ($p) => $norm($p->nom_complet) === $norm($d->nom_complet));
            if ($match) {
                DB::table('personnels')->where('id', $match->id)->update($fields + ($match->fonction === 'autre' ? ['fonction' => 'chauffeur'] : []));
            } else {
                DB::table('personnels')->insert($fields + [
                    'id' => (string) Str::uuid7(), 'district_id' => $d->district_id, 'nom_complet' => $d->nom_complet, 'fonction' => 'chauffeur',
                    'telephone' => $d->telephone, 'email' => $d->email ?? null, 'statut' => $d->statut === 'inactif' ? 'inactif' : 'actif', 'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('personnels')->where('fonction', 'chauffeur')->delete();
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('vehicule_principal_id');
            $table->dropColumn(['matricule', 'categorie_permis', 'numero_permis', 'permis_expiration', 'date_obtention_permis']);
        });
    }
};
