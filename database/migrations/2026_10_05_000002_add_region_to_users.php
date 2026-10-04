<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un responsable régional est rattaché à une région : il accède à tous les districts de cette région.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('region_id')->nullable()->constrained('regions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('region_id'));
    }
};
