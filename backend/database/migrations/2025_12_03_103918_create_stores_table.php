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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Mağaza sahibi
            
            // Shopify Bilgileri
            $table->string('domain')->unique(); // shop.myshopify.com
            $table->string('access_token')->nullable();
            $table->string('shop_owner')->nullable();
            $table->string('email')->nullable();
            
            // KolaySoft Entegrasyonu
            $table->string('kolaysoft_username')->nullable();
            $table->string('kolaysoft_password')->nullable();
            $table->string('kolaysoft_vkn_tckn')->nullable(); // VKN veya TCKN
            $table->string('kolaysoft_supplier_name')->nullable(); // Faturada görünecek firma adı
            
            // Genel Ayarlar
            $table->string('currency')->default('TRY');
            $table->string('locale')->default('tr'); // Dil (tr, en)
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
