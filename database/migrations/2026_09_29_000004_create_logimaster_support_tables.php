<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents (polymorphique: vehicle, driver, sortie)
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('documentable_type', 40); // vehicle | driver | sortie
            $table->uuid('documentable_id');
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('categorie', 50); // carte_grise, assurance, permis, facture...
            $table->text('fichier_url');
            $table->date('date_expiration')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['documentable_type', 'documentable_id']);
        });

        // Dépenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sortie_id')->nullable()->constrained('sorties_vehicules')->nullOnDelete();
            $table->string('type', 40); // carburant, maintenance, peage, autre
            $table->string('beneficiaire', 120)->nullable();
            $table->decimal('montant', 12, 2);
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });

        // Budgets par district et période
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // ex: 2026-09-01 (premier du mois)
            $table->decimal('montant_alloue', 14, 2);
            $table->timestamps();
            $table->unique(['district_id', 'period']);
        });

        // Snapshots indicateurs DDKM
        Schema::create('indicator_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_key', 50); // distance_totale, taux_immobilisation...
            $table->date('period');
            $table->decimal('value', 14, 4);
            $table->json('breakdown')->nullable(); // détail JSON
            $table->timestamp('computed_at')->useCurrent();
            $table->timestamps();
            $table->unique(['district_id', 'indicator_key', 'period']);
            $table->index(['district_id', 'indicator_key', 'period']);
        });

        // Audit logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('module', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('indicator_snapshots');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('documents');
    }
};
