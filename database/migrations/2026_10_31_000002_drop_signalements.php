<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Les signalements terrain (pannes et vidanges déclarées depuis le téléphone par les convoyeurs) n'existent plus. */
    public function up(): void
    {
        Schema::dropIfExists('signalements');
    }

    public function down(): void
    {
        // table supprimée définitivement
    }
};
