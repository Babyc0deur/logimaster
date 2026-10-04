<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module 2 — fiche véhicule complète
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('annee_circulation')->nullable();
            $table->unsignedInteger('poids_vide')->nullable();          // kg
            $table->unsignedInteger('capacite_charge')->nullable();     // kg
            $table->decimal('volume_utile', 6, 2)->nullable();          // m³
            $table->string('appartenance', 20)->default('district');    // district, particulier, location, mutualisation
            $table->string('bailleur', 120)->nullable();
            $table->date('date_reception')->nullable();
            $table->decimal('prix_carburant', 10, 2)->nullable();       // FCFA / L
            $table->date('date_dernier_releve')->nullable();
        });

        // Module 8 (partiel) — chefs de mission et passagers
        Schema::create('personnels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('nom_complet', 120);
            $table->string('fonction', 30)->default('passager'); // chef_mission, passager, autre
            $table->string('telephone', 30)->nullable();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();
        });

        // Module 3 — formulaire de sortie complet
        Schema::table('sorties_vehicules', function (Blueprint $table) {
            $table->date('date_sortie')->nullable();
            $table->foreignUuid('chef_mission_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->string('point_depart', 160)->nullable();
            $table->string('point_arrivee', 160)->nullable();
            $table->json('etapes')->nullable(); // [{lieu, km}]
            $table->timestamp('validated_at')->nullable();
            $table->foreignUuid('validated_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('sortie_personnel', function (Blueprint $table) {
            $table->foreignUuid('sortie_id')->constrained('sorties_vehicules')->cascadeOnDelete();
            $table->foreignUuid('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->primary(['sortie_id', 'personnel_id']);
        });
        DB::table('sorties_vehicules')->whereNull('date_sortie')->update(['date_sortie' => DB::raw('date(created_at)')]);

        // Module 4 — ravitaillements (facture, validation, anomalies) et paramètres carburant
        Schema::table('ravitaillements', function (Blueprint $table) {
            $table->text('facture_path')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('anomalie', 40)->nullable(); // surconsommation, km_incoherent
        });
        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type_carburant', 20); // essence, diesel, hybride
            $table->decimal('prix', 10, 2);
            $table->date('date_effet');
            $table->timestamps();
            $table->index(['type_carburant', 'date_effet']);
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Module 5 — vidanges et immobilisations
        Schema::table('vidanges', function (Blueprint $table) {
            $table->date('prochaine_date')->nullable();
            $table->text('observations')->nullable();
            $table->text('facture_path')->nullable();
        });
        Schema::table('immobilisations', function (Blueprint $table) {
            $table->string('numero_facture', 60)->nullable();
            $table->text('facture_path')->nullable();
        });
        DB::table('immobilisations')->where('statut', 'resolu')->update(['statut' => 'terminee']);

        // Notifications in-app (Filament database notifications)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('immobilisations', fn (Blueprint $t) => $t->dropColumn(['numero_facture', 'facture_path']));
        Schema::table('vidanges', fn (Blueprint $t) => $t->dropColumn(['prochaine_date', 'observations', 'facture_path']));
        Schema::dropIfExists('settings');
        Schema::dropIfExists('fuel_prices');
        Schema::table('ravitaillements', function (Blueprint $t) {
            $t->dropConstrainedForeignId('valide_par');
            $t->dropColumn(['facture_path', 'valide_at', 'anomalie']);
        });
        Schema::dropIfExists('sortie_personnel');
        Schema::table('sorties_vehicules', function (Blueprint $t) {
            $t->dropConstrainedForeignId('chef_mission_id');
            $t->dropConstrainedForeignId('validated_by');
            $t->dropColumn(['date_sortie', 'point_depart', 'point_arrivee', 'etapes', 'validated_at']);
        });
        Schema::dropIfExists('personnels');
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn([
            'annee_circulation', 'poids_vide', 'capacite_charge', 'volume_utile', 'appartenance',
            'bailleur', 'date_reception', 'prix_carburant', 'date_dernier_releve',
        ]));
    }
};
