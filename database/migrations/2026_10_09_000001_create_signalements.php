<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Signalements faits depuis l'application convoyeur (vidange réalisée, panne / immobilisation), validés ensuite par le bureau. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);                       // vidange | panne
            $table->unsignedInteger('km')->nullable();
            $table->text('description')->nullable();
            $table->json('details')->nullable();              // vidange : type_vidange, montant, prestataire ; panne : motif
            $table->string('photo')->nullable();
            $table->string('statut', 20)->default('nouveau');  // nouveau | valide | rejete
            $table->foreignUuid('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_at')->nullable();
            $table->string('commentaire_bureau', 500)->nullable();
            $table->nullableUuidMorphs('cible');               // vidange ou immobilisation créée à la validation
            $table->string('client_ref', 64)->nullable()->unique();
            $table->timestamp('signale_at')->nullable();
            $table->timestamps();
            $table->index(['district_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements');
    }
};
