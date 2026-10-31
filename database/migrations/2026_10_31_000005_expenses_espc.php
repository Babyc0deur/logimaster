<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Centre de santé bénéficiaire d'un frais (indemnité de déplacement pour un enlèvement au district par l'ESPC). */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUuid('espc_id')->nullable()->after('vehicle_id')->constrained('espc')->nullOnDelete();
            $table->index(['district_id', 'type', 'espc_id']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['district_id', 'type', 'espc_id']);
            $table->dropConstrainedForeignId('espc_id');
        });
    }
};
