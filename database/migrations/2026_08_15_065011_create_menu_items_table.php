<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->json('name'); // {"en": "Ensalada Bowl", "es": "Bowl Ensalada"}
            $table->string('slug')->unique(); // always derived from name.en
            $table->json('description'); // doubles as the ingredients list, bilingual
            $table->decimal('price', 8, 2);
            $table->string('image');
            $table->string('image_public_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};