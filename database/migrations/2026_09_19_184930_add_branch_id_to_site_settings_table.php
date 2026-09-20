<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every setting (hero copy, about text, stats, contact info, social
     * links) becomes branch-specific — each branch gets its own full set
     * of rows for the same keys.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);

            $table->foreignId('branch_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique(['branch_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'key']);
            $table->dropConstrainedForeignId('branch_id');
            $table->unique('key');
        });
    }
};
