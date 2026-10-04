<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Véhicules
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('immatriculation', 20)->unique();
            $table->string('marque', 60)->nullable();
            $table->string('modele', 60)->nullable();
            $table->string('type_vehicule', 40)->nullable(); // camion, moto, 4x4...
            $table->string('type_carburant', 20)->default('essence'); // essence, diesel, hybride
            $table->decimal('consommation_theorique', 6, 2)->nullable(); // L/100km
            $table->integer('km_actuel')->default(0);
            $table->integer('km_vidange')->nullable(); // prochain km vidange
            $table->date('date_ct')->nullable(); // contrôle technique
            $table->date('date_assurance')->nullable();
            $table->string('statut', 20)->default('disponible'); // disponible, en_mission, en_maintenance, hors_service
            $table->bigInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        // Chauffeurs
        Schema::create('drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('matricule', 30)->unique();
            $table->string('nom_complet', 120);
            $table->string('telephone', 30)->nullable();
            $table->string('categorie_permis', 10)->nullable(); // B, C, D...
            $table->date('permis_expiration')->nullable();
            $table->string('statut', 20)->default('actif'); // actif, inactif, suspendu
            $table->bigInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        // Circuits de distribution
        Schema::create('circuits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 120);
            $table->decimal('distance_totale', 8, 2)->nullable(); // km
            $table->integer('temps_estime_min')->nullable(); // minutes
            $table->string('frequence', 20)->nullable(); // hebdomadaire, mensuel...
            $table->string('statut', 20)->default('actif');
            $table->timestamps();
        });

        // Établissements Sanitaires de Premier Contact (ESPC)
        Schema::create('espc', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 160);
            $table->string('type', 40)->nullable(); // centre_sante, hopital, dispensaire...
            $table->decimal('gps_lat', 9, 6)->nullable();
            $table->decimal('gps_lon', 9, 6)->nullable();
            $table->string('responsable', 120)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();
        });

        // Pivot circuit-espc (ordre des arrêts)
        Schema::create('circuit_espc', function (Blueprint $table) {
            $table->foreignUuid('circuit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('espc_id')->constrained('espc')->cascadeOnDelete();
            $table->integer('ordre');
            $table->primary(['circuit_id', 'espc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_espc');
        Schema::dropIfExists('espc');
        Schema::dropIfExists('circuits');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
    }
};
