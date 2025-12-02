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
        Schema::create('xml_integrations', function (Blueprint $table) {
            $table->id();
            
            // Hangi mağazaya ait? (Shopify User ID)
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // XML Bilgileri
            $table->string('name')->nullable(); // Örn: Tedarikçi A
            $table->string('xml_url'); // XML Linki
            
            // Eşleştirmeler (Mapping) - JSON olarak tutacağız
            // Örn: {"title": "UrunAdi", "price": "SatisFiyati", "sku": "StokKodu"}
            $table->json('field_mapping')->nullable(); 

            // Ayarlar
            $table->boolean('is_active')->default(true); // Aktif mi?
            $table->string('sync_frequency')->default('daily'); // Güncelleme sıklığı
            $table->timestamp('last_synced_at')->nullable(); // Son güncelleme zamanı

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xml_integrations');
    }
};
