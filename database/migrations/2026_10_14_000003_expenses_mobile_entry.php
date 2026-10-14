<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Autres frais saisis depuis l'application mobile : référence d'envoi (évite les doublons si l'envoi est rejoué) et auteur de la saisie. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('client_ref', 64)->nullable()->unique();
            $table->string('saisi_par')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['client_ref']);
            $table->dropColumn(['client_ref', 'saisi_par']);
        });
    }
};
