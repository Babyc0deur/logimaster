<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modifier la table users pour LogiMaster
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('remember_token');
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
        });

        // PRES (niveau national)
        Schema::create('pres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->timestamps();
        });

        // Régions
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pres_id')->constrained('pres')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
        });

        // Districts (tenant principal)
        Schema::create('districts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('sync_id', 20)->unique();
            $table->string('sync_password_hash', 255);
            $table->timestamp('sync_password_rotated_at')->nullable();
            $table->timestamps();
        });

        // Pivot user-district
        Schema::create('district_user', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'district_id']);
        });

        // Appareils Electron par district
        Schema::create('district_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('district_id')->constrained()->cascadeOnDelete();
            $table->string('device_name', 120)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('district_devices');
        Schema::dropIfExists('district_user');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('pres');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'two_factor_enabled']);
        });
    }
};
