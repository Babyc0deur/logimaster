<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chaque chef de mission / passager actif est convoyeur d'office : identifiant de connexion généré automatiquement
        // et code d'accès provisoire (chiffré, effacé dès que la personne choisit son mot de passe).
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('identifiant', 80)->nullable()->unique();
            $table->text('code_acces')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn(['identifiant', 'code_acces']);
        });
    }
};
