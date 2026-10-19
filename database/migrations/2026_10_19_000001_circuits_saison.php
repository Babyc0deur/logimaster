<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saison de validité d'un circuit : toute l'année, saison sèche ou saison pluvieuse. */
    public function up(): void
    {
        Schema::table('circuits', function (Blueprint $table) {
            $table->string('saison', 20)->default('toute_annee')->after('frequence');
        });
    }

    public function down(): void
    {
        Schema::table('circuits', fn (Blueprint $table) => $table->dropColumn('saison'));
    }
};
