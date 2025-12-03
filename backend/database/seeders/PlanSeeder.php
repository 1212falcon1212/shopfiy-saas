<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Önce temizle (Gerekirse)
        // Plan::truncate();

        $plans = [
            [
                'name' => json_encode(['tr' => 'Başlangıç', 'en' => 'Starter']),
                'description' => json_encode(['tr' => 'Yeni başlayanlar için temel özellikler', 'en' => 'Basic features for starters']),
                'price_try' => 199.90,
                'price_usd' => 9.90,
                'price_eur' => 8.90,
                'interval' => 'monthly',
                'features' => json_encode([
                    'tr' => ['1 Mağaza', 'Temel İstatistikler', 'KolaySoft Entegrasyonu'],
                    'en' => ['1 Store', 'Basic Statistics', 'KolaySoft Integration']
                ]),
                'is_active' => true,
            ],
            [
                'name' => json_encode(['tr' => 'Profesyonel', 'en' => 'Professional']),
                'description' => json_encode(['tr' => 'Büyüyen işletmeler için gelişmiş özellikler', 'en' => 'Advanced features for growing businesses']),
                'price_try' => 399.90,
                'price_usd' => 19.90,
                'price_eur' => 17.90,
                'interval' => 'monthly',
                'features' => json_encode([
                    'tr' => ['5 Mağaza', 'Gelişmiş Raporlama', 'Öncelikli Destek', 'Tüm Entegrasyonlar'],
                    'en' => ['5 Stores', 'Advanced Reporting', 'Priority Support', 'All Integrations']
                ]),
                'is_active' => true,
            ],
            [
                'name' => json_encode(['tr' => 'Yıllık Başlangıç', 'en' => 'Annual Starter']),
                'description' => json_encode(['tr' => 'Yıllık ödeme ile avantajlı fiyat', 'en' => 'Discounted price with annual payment']),
                'price_try' => 1999.00,
                'price_usd' => 99.00,
                'price_eur' => 89.00,
                'interval' => 'yearly',
                'features' => json_encode([
                    'tr' => ['1 Mağaza', 'Temel İstatistikler', 'KolaySoft Entegrasyonu', '2 Ay Bedava'],
                    'en' => ['1 Store', 'Basic Statistics', 'KolaySoft Integration', '2 Months Free']
                ]),
                'is_active' => true,
            ]
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
