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
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tema Adı (Örn: Modern Fashion)
            $table->string('slug')->unique(); // modern-fashion
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable(); // Önizleme resmi
            
            // Temanın sunucumuzdaki fiziksel yolu (Örn: storage/themes/modern-v1)
            $table->string('folder_path'); 
            
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2)->default(0); // Ücretli satacaksan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
