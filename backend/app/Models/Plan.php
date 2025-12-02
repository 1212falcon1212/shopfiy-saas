<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Osiset\ShopifyApp\Storage\Models\Plan as ShopifyPlan;

class Plan extends ShopifyPlan
{
    use HasFactory;

    // Paketin varsayılan tablosunu kullanmak için
    protected $table = 'plans';
}
