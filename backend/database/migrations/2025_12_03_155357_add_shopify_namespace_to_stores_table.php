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
            // ShopModel trait'inin beklediği kolonlar
            $table->string('shopify_namespace')->nullable()->after('access_token');
            $table->boolean('shopify_grandfathered')->default(false)->after('shopify_namespace');
            $table->boolean('shopify_freemium')->default(false)->after('shopify_grandfathered');
            $table->softDeletes(); // deleted_at kolonu (soft deletes için)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['shopify_namespace', 'shopify_grandfathered', 'shopify_freemium']);
            $table->dropSoftDeletes();
        });
    }
};
