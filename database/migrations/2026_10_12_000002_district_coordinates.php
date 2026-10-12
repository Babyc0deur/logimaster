<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chef-lieu de chaque district sanitaire (latitude, longitude) : carte du suivi de la saisie, cartographie. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('districts', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
    }
};
