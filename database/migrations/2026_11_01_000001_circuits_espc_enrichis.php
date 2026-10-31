<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Circuits : décomposition du temps, version, urgence, site livré via un point de transit. ESPC : caractéristiques de desserte. */
    public function up(): void
    {
        Schema::table('circuits', function (Blueprint $table) {
            $table->unsignedInteger('temps_aller_min')->nullable()->after('temps_estime_min');
            $table->unsignedInteger('temps_retour_min')->nullable()->after('temps_aller_min');
            $table->unsignedInteger('temps_depot_min')->nullable()->after('temps_retour_min');
            $table->unsignedInteger('temps_coaching_min')->nullable()->after('temps_depot_min');
            $table->string('version', 20)->default('existant')->after('saison');
            $table->boolean('urgence')->default(false)->after('version');
        });

        Schema::table('circuit_espc', function (Blueprint $table) {
            $table->uuid('livre_via_espc_id')->nullable();
        });

        Schema::table('espc', function (Blueprint $table) {
            $table->decimal('distance_aller_retour_km', 8, 1)->nullable();
            $table->unsignedInteger('colis_mois')->nullable();
            $table->decimal('poids_mois_kg', 9, 1)->nullable();
            $table->string('type_voie', 20)->nullable();
            $table->string('etat_voie', 20)->nullable();
            $table->string('etat_voie_pluie', 20)->nullable();
            $table->json('mois_mauvais')->nullable();
            $table->boolean('point_transit')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('espc', fn (Blueprint $t) => $t->dropColumn(['distance_aller_retour_km', 'colis_mois', 'poids_mois_kg', 'type_voie', 'etat_voie', 'etat_voie_pluie', 'mois_mauvais', 'point_transit']));
        Schema::table('circuit_espc', fn (Blueprint $t) => $t->dropColumn('livre_via_espc_id'));
        Schema::table('circuits', fn (Blueprint $t) => $t->dropColumn(['temps_aller_min', 'temps_retour_min', 'temps_depot_min', 'temps_coaching_min', 'version', 'urgence']));
    }
};
