<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price and availability are no longer global — they now live on the
     * branch_menu_item pivot table, since the same item can cost a
     * different amount (or not be offered at all) at each branch.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->default(0)->after('description');
            $table->boolean('is_available')->default(true)->after('price');
        });
    }
};
