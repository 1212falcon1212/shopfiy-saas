<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Fatura Durumu: 'pending', 'processing', 'completed', 'failed'
            $table->string('invoice_status')->default('pending')->after('financial_status'); 
            
            // KolaySoft'tan dönecek Fatura No / ETTN
            $table->string('invoice_number')->nullable()->after('invoice_status');
            
            // KolaySoft tarafındaki ID (UUID olabilir)
            $table->string('invoice_external_id')->nullable()->after('invoice_number');
            
            // PDF Linki (Varsa)
            $table->string('invoice_url')->nullable()->after('invoice_external_id');
            
            // Hata mesajı (Fatura kesilemezse nedenini görmek için)
            $table->text('invoice_error')->nullable()->after('invoice_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_status', 'invoice_number', 'invoice_external_id', 'invoice_url', 'invoice_error']);
        });
    }
};