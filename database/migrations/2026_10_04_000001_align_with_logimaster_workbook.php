<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chronogramme du classeur : une sortie planifiée = circuit + date + liste ordonnée de sites ;
        // le véhicule et le chauffeur ne sont affectés qu'au moment de la sortie.
        Schema::table('chronogrammes', function (Blueprint $table) {
            $table->foreignUuid('vehicle_id')->nullable()->change();
        });
        Schema::create('chronogramme_espc', function (Blueprint $table) {
            $table->foreignUuid('chronogramme_id')->constrained('chronogrammes')->cascadeOnDelete();
            $table->foreignUuid('espc_id')->constrained('espc')->cascadeOnDelete();
            $table->unsignedInteger('ordre');
            $table->primary(['chronogramme_id', 'espc_id']);
        });

        // Onglet VEHICULES
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('vignette_annee')->nullable();
            $table->date('date_dernier_ct')->nullable();
            $table->text('commentaire')->nullable();
        });

        // Dates métier (les indicateurs se calculent sur la date de l'opération, pas sur la date de saisie)
        Schema::table('ravitaillements', function (Blueprint $table) {
            $table->date('date_ravitaillement')->nullable()->index();
            $table->string('motif', 50)->nullable();
        });
        DB::table('ravitaillements')->whereNull('date_ravitaillement')->update(['date_ravitaillement' => DB::raw('date(created_at)')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->date('date_depense')->nullable()->index();
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('motif', 50)->nullable();
        });
        DB::table('expenses')->whereNull('date_depense')->update(['date_depense' => DB::raw('date(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['date_depense', 'motif']);
        });
        Schema::table('ravitaillements', fn (Blueprint $t) => $t->dropColumn(['date_ravitaillement', 'motif']));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn(['vignette_annee', 'date_dernier_ct', 'commentaire']));
        Schema::dropIfExists('chronogramme_espc');
    }
};
