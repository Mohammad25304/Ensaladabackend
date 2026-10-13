<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Grams per serving. `calories` is stored in kcal.
            $table->unsignedInteger('fats_grams')->nullable()->after('protein_grams');
            $table->unsignedInteger('carbs_grams')->nullable()->after('fats_grams');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['fats_grams', 'carbs_grams']);
        });
    }
};
