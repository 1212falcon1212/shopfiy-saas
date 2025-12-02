<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Önce temizle (FK kontrolünü kapatarak)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plans')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Pro Plan Ekle
        DB::table('plans')->insert([
            'type' => 'RECURRING', // Aylık Abonelik
            'name' => 'Pro Plan',
            'price' => 19.90,
            'interval' => 'EVERY_30_DAYS',
            'capped_amount' => 0.00, // Ekstra ücret yok
            'terms' => 'Aylık 19.90$ karşılığında tüm özelliklere erişim.',
            'trial_days' => 3, // 3 Gün Deneme
            'test' => true, // Test modunda (Gerçek para çekmez)
            'on_install' => true, // Kurulumda bu planı öner
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
