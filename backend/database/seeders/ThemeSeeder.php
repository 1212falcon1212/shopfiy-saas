<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::create([
            'name' => 'SaaS Demo Tema',
            'slug' => 'saas-demo-v1',
            'description' => 'Laravel paneli üzerinden yüklenen test teması.',
            'folder_path' => 'themes/demo-theme', // storage/app altındaki yol
            'price' => 0,
            'is_active' => true
        ]);
    }
}