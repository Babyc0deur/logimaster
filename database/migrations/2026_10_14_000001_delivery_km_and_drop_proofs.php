<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livraison d'un site : le téléphone relève la position GPS du véhicule et le kilométrage au compteur.
 * Le réceptionnaire, le nombre de colis et la photo du bon signé ne sont plus saisis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->unsignedInteger('km_compteur')->nullable();
        });
        Schema::table('livraisons_espc', fn (Blueprint $t) => $t->dropColumn(['receptionnaire', 'colis', 'preuve_photo']));
    }

    public function down(): void
    {
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->string('receptionnaire', 120)->nullable();
            $table->unsignedInteger('colis')->nullable();
            $table->string('preuve_photo')->nullable();
        });
        Schema::table('livraisons_espc', fn (Blueprint $t) => $t->dropColumn('km_compteur'));
    }
};
