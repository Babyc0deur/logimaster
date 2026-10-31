<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mutualisation : un véhicule d'un district (le propriétaire, `vehicles.district_id`) mis à la disposition d'un autre district
     * (l'utilisateur, `district_id` ci-dessous) pendant une période. Les deux districts voient l'activité du véhicule.
     */
    public function up(): void
    {
        Schema::create('vehicle_mutualisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignUuid('district_id')->constrained('districts')->cascadeOnDelete();   // district utilisateur
            $table->date('date_debut');
            $table->date('date_fin')->nullable();                                                // vide = sans date de fin
            $table->string('motif', 255)->nullable();
            $table->string('statut', 20)->default('active');                                     // active | terminee
            $table->foreignUuid('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehicle_id', 'statut']);
            $table->index(['district_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_mutualisations');
    }
};
