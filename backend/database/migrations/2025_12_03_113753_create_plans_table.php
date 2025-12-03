<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {"tr": "Başlangıç", "en": "Starter"}
            $table->json('description')->nullable();
            $table->decimal('price_try', 10, 2);
            $table->decimal('price_usd', 10, 2);
            $table->decimal('price_eur', 10, 2);
            $table->enum('interval', ['monthly', 'yearly'])->default('monthly');
            $table->json('features')->nullable(); // ["Özellik 1", "Feature 2"]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_plans');
    }
};
