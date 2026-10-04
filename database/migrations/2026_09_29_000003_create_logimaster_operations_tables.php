<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sorties véhicules (missions/trajets)
        Schema::create('sorties_vehicules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type', 20)->default('district'); // driver | district
            $table->uuid('owner_id');
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('circuit_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('km_depart');
            $table->integer('km_arrivee')->nullable();
            $table->string('motif', 50); // distribution, administrative, urgence...
            $table->string('destination', 160)->nullable();
            $table->boolean('circuit_respecte')->nullable();
            $table->text('commentaires')->nullable();
            $table->string('statut', 20)->default('en_cours'); // en_cours, terminee, annulee
            $table->bigInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['district_id', 'updated_at']);
            $table->index(['owner_id', 'updated_at']);
        });

        // Ravitaillements carburant
        Schema::create('ravitaillements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type', 20)->default('district');
            $table->uuid('owner_id');
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sortie_id')->nullable()->constrained('sorties_vehicules')->nullOnDelete();
            $table->foreignUuid('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('litres', 8, 2);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('montant_total', 12, 2)->virtualAs('litres * prix_unitaire');
            $table->string('station', 120)->nullable();
            $table->string('numero_facture', 60)->nullable();
            $table->integer('km_compteur')->nullable();
            $table->bigInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['district_id', 'updated_at']);
        });

        // Immobilisations (pannes, réparations)
        Schema::create('immobilisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type', 20)->default('district');
            $table->uuid('owner_id');
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('motif', 50); // panne, entretien, accident, controle_technique
            $table->text('description')->nullable();
            $table->decimal('montant', 12, 2)->nullable();
            $table->string('prestataire', 120)->nullable();
            $table->string('statut', 20)->default('en_cours'); // en_cours, resolu
            $table->bigInteger('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['district_id', 'updated_at']);
        });

        // Vidanges (maintenance préventive)
        Schema::create('vidanges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('km');
            $table->string('type', 30)->nullable(); // vidange, filtre, pneus...
            $table->decimal('montant', 12, 2)->nullable();
            $table->string('prestataire', 120)->nullable();
            $table->string('numero_facture', 60)->nullable();
            $table->integer('prochain_km')->nullable();
            $table->timestamps();
        });

        // Sync operations (idempotence)
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('client_op_id')->primary();
            $table->uuid('device_id');
            $table->uuid('owner_id');
            $table->string('entity_table');
            $table->uuid('entity_id');
            $table->timestamp('applied_at')->useCurrent();
            $table->index(['owner_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('vidanges');
        Schema::dropIfExists('immobilisations');
        Schema::dropIfExists('ravitaillements');
        Schema::dropIfExists('sorties_vehicules');
    }
};
