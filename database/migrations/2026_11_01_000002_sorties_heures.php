<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Heures réelles de départ et d'arrivée de la sortie (colonnes du carnet de bord). */
    public function up(): void
    {
        Schema::table('sorties_vehicules', function (Blueprint $table) {
            $table->time('heure_depart')->nullable();
            $table->time('heure_arrivee')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sorties_vehicules', fn (Blueprint $table) => $table->dropColumn(['heure_depart', 'heure_arrivee']));
    }
};
