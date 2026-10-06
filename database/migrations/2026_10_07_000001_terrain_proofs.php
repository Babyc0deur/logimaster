<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preuves de terrain saisies dans l'application convoyeur :
 * - photo du compteur au départ et au retour de la sortie ;
 * - preuve de livraison par site : réceptionnaire, nombre de colis, photo (bon signé), précision du GPS et écart avec la
 *   position connue du centre ;
 * - origine de la position GPS des ESPC (relevée automatiquement à la première livraison sur place).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorties_vehicules', function (Blueprint $table) {
            $table->string('photo_km_depart')->nullable();
            $table->string('photo_km_arrivee')->nullable();
        });
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->string('receptionnaire', 120)->nullable();
            $table->unsignedInteger('colis')->nullable();
            $table->string('preuve_photo')->nullable();
            $table->unsignedInteger('gps_precision_m')->nullable();
            $table->unsignedInteger('gps_ecart_m')->nullable();
        });
        Schema::table('espc', function (Blueprint $table) {
            $table->string('gps_source', 20)->nullable();   // import | manuel | livraison
            $table->timestamp('gps_releve_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sorties_vehicules', fn (Blueprint $t) => $t->dropColumn(['photo_km_depart', 'photo_km_arrivee']));
        Schema::table('livraisons_espc', fn (Blueprint $t) => $t->dropColumn(['receptionnaire', 'colis', 'preuve_photo', 'gps_precision_m', 'gps_ecart_m']));
        Schema::table('espc', fn (Blueprint $t) => $t->dropColumn(['gps_source', 'gps_releve_at']));
    }
};
