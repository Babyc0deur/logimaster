<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Chronogramme : validation par un superviseur, reprogrammation, raison de non-réalisation
        Schema::table('chronogrammes', function (Blueprint $table) {
            $table->text('raison')->nullable();                                   // pourquoi reportée / annulée
            $table->string('validation_statut', 20)->default('brouillon');        // brouillon, soumis, valide, refuse
            $table->timestamp('soumis_at')->nullable();
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->text('motif_refus')->nullable();
            $table->date('date_initiale')->nullable();                            // date avant reprogrammation (glisser-déposer)
        });

        // ---- Suivi des livraisons par site : l'ancienne table pivot devient une vraie table (id) avec statut de livraison
        Schema::create('livraisons_espc', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chronogramme_id')->constrained('chronogrammes')->cascadeOnDelete();
            $table->foreignUuid('espc_id')->constrained('espc')->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->string('statut', 20)->default('planifie');                    // planifie, livre, non_livre
            $table->date('date_livraison')->nullable();
            $table->string('lieu_livraison', 20)->nullable();                     // site, transit
            $table->text('raison_non_livraison')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->unique(['chronogramme_id', 'espc_id']);
            $table->index(['espc_id', 'statut']);
        });
        foreach (DB::table('chronogramme_espc')->get() as $row) {
            DB::table('livraisons_espc')->insert([
                'id' => (string) Str::uuid(), 'chronogramme_id' => $row->chronogramme_id, 'espc_id' => $row->espc_id,
                'ordre' => $row->ordre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Schema::drop('chronogramme_espc');

        // ---- Circuits et ESPC : étapes avec distance, point de départ, localisation, contact
        Schema::table('circuit_espc', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable();                      // distance depuis l'étape précédente
        });
        Schema::table('circuits', function (Blueprint $table) {
            $table->string('point_depart', 160)->nullable();
            $table->decimal('depart_lat', 9, 6)->nullable();
            $table->decimal('depart_lon', 9, 6)->nullable();
        });
        Schema::table('espc', function (Blueprint $table) {
            $table->string('adresse', 200)->nullable();
            $table->string('email', 160)->nullable();
        });

        // ---- Personnel
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('email', 160)->nullable();
            $table->string('numero_permis', 40)->nullable();
            $table->date('date_obtention_permis')->nullable();
            $table->foreignUuid('vehicule_principal_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('email', 160)->nullable();
        });

        // ---- Finance : budgets par poste et par bailleur
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['district_id', 'period']);
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('poste', 20)->default('global');                        // global, carburant, maintenance, autres
            $table->string('bailleur', 120)->default('');                          // '' = tous bailleurs
            $table->unique(['district_id', 'period', 'poste', 'bailleur']);
        });

        // ---- Finance : factures et leur workflow
        Schema::create('factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 60);
            $table->string('fournisseur', 160);
            $table->date('date_facture');
            $table->string('categorie', 20);                                       // carburant, maintenance, autres
            $table->decimal('montant', 14, 2);
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('bailleur', 120)->nullable();
            $table->text('description')->nullable();
            $table->text('fichier_path')->nullable();
            $table->string('statut', 20)->default('brouillon');                   // brouillon, a_valider, validee, rejetee, payee, archivee
            $table->foreignUuid('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('soumise_at')->nullable();
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->foreignUuid('paye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paye_at')->nullable();
            $table->string('mode_paiement', 30)->nullable();
            $table->string('reference_paiement', 80)->nullable();
            $table->timestamp('archive_at')->nullable();
            $table->json('historique')->nullable();                                // [{action, par, le, note}]
            $table->timestamps();
            $table->unique(['district_id', 'fournisseur', 'numero']);
            $table->index(['district_id', 'statut']);
        });

        // ---- Rapports générés et planifiés
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);                                            // ddkm, flotte, carburant, maintenance, financier, bailleur
            $table->string('format', 10);                                          // pdf, xlsx
            $table->string('periode', 7);                                          // YYYY-MM
            $table->json('scope')->nullable();                                     // {district_id|region_id|pres_id}
            $table->string('titre', 200);
            $table->text('fichier_path')->nullable();
            $table->string('statut', 20)->default('genere');
            $table->timestamps();
        });
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('formats');                                               // ["pdf","xlsx"]
            $table->json('scope')->nullable();
            $table->string('frequence', 12)->default('mensuelle');                 // hebdomadaire, mensuelle
            $table->unsignedTinyInteger('jour')->default(1);                       // jour du mois (1-28) ou de la semaine (1-7)
            $table->json('destinataires');                                         // emails
            $table->boolean('actif')->default(true);
            $table->timestamp('dernier_envoi_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('factures');
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['district_id', 'period', 'poste', 'bailleur']);
            $table->dropColumn(['poste', 'bailleur']);
        });
        Schema::table('budgets', fn (Blueprint $t) => $t->unique(['district_id', 'period']));
        Schema::table('personnels', fn (Blueprint $t) => $t->dropColumn('email'));
        Schema::table('drivers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vehicule_principal_id');
            $t->dropColumn(['email', 'numero_permis', 'date_obtention_permis']);
        });
        Schema::table('espc', fn (Blueprint $t) => $t->dropColumn(['adresse', 'email']));
        Schema::table('circuits', fn (Blueprint $t) => $t->dropColumn(['point_depart', 'depart_lat', 'depart_lon']));
        Schema::table('circuit_espc', fn (Blueprint $t) => $t->dropColumn('distance_km'));
        Schema::dropIfExists('livraisons_espc');
        Schema::table('chronogrammes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('valide_par');
            $t->dropColumn(['raison', 'validation_statut', 'soumis_at', 'valide_at', 'motif_refus', 'date_initiale']);
        });
    }
};
