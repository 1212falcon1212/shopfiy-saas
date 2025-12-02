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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Hangi mağazanın siparişi?
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Shopify Verileri
            $table->unsignedBigInteger('shopify_order_id')->unique(); // Shopify'daki ID
            $table->string('order_number'); // Sipariş No (#1001)

            // Müşteri Bilgileri
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('shipping_address')->nullable(); // Adres detayları JSON olarak

            // Tutar ve Durum
            $table->decimal('total_price', 10, 2);
            $table->string('currency')->default('TRY');
            $table->string('financial_status')->default('pending'); // Ödendi mi?
            $table->string('fulfillment_status')->nullable(); // Kargolandı mı?

            // Sipariş İçeriği (Ürünler) - JSON olarak tutmak pratik
            $table->json('line_items')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
