<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le convoyeur (chef de mission ou passager) se connecte avec un compte rattaché à sa fiche personnel.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('personnel_id')->nullable()->constrained('personnels')->nullOnDelete();
        });

        // Équipe prévue sur une sortie planifiée : seuls ces convoyeurs voient la sortie dans l'application mobile.
        Schema::create('chronogramme_personnel', function (Blueprint $table) {
            $table->foreignUuid('chronogramme_id')->constrained('chronogrammes')->cascadeOnDelete();
            $table->foreignUuid('personnel_id')->constrained('personnels')->cascadeOnDelete();
            $table->primary(['chronogramme_id', 'personnel_id']);
        });

        // Qui a saisi la livraison, quand et d'où (positionnement facultatif du téléphone).
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->foreignUuid('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('saisi_at')->nullable();
        });

        // Référence générée par le téléphone : renvoyer un plein enregistré hors réseau ne le crée pas deux fois.
        Schema::table('ravitaillements', function (Blueprint $table) {
            $table->string('client_ref', 64)->nullable()->unique();
            $table->foreignUuid('saisi_par')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('ravitaillements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saisi_par');
            $table->dropColumn('client_ref');
        });
        Schema::table('livraisons_espc', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saisi_par');
            $table->dropColumn(['lat', 'lon', 'saisi_at']);
        });
        Schema::dropIfExists('chronogramme_personnel');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personnel_id');
        });
    }
};
