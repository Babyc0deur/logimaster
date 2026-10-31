<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Heures d'arrivée et de départ sur chaque site : temps passé sur place et durée réelle de la tournée. */
    public function up(): void
    {
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('livraisons_espc', fn (Blueprint $table) => $table->dropColumn(['heure_arrivee', 'heure_depart']));
    }
};
