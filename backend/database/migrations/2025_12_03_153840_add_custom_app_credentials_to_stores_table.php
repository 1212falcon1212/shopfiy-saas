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
        Schema::table('stores', function (Blueprint $table) {
            // Custom App Credentials (Her kullanıcı kendi Custom App'ini oluşturur)
            $table->string('shopify_client_id')->nullable()->after('domain');
            $table->string('shopify_client_secret')->nullable()->after('shopify_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['shopify_client_id', 'shopify_client_secret']);
        });
    }
};
