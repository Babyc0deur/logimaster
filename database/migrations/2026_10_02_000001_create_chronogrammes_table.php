<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chronogramme : planification des sorties de véhicules
        Schema::create('chronogrammes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('circuit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_prevue');
            $table->time('heure_depart')->nullable();
            $table->string('motif', 50)->default('distribution');
            $table->string('destination', 160)->nullable();
            $table->string('statut', 20)->default('planifiee'); // planifiee, realisee, annulee, reportee
            $table->foreignUuid('sortie_id')->nullable()->constrained('sorties_vehicules')->nullOnDelete();
            $table->text('commentaires')->nullable();
            $table->timestamps();
            $table->index(['district_id', 'date_prevue']);
            $table->index(['vehicle_id', 'date_prevue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chronogrammes');
    }
};
