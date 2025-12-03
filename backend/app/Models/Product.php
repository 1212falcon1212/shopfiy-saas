<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id', // Yeni eklendi
        'shopify_product_id',
        'title',
        'body_html',
        'vendor',
        'product_type',
        'image_src',
        'variants'
    ];

    protected $casts = [
        'variants' => 'array', // JSON veriyi PHP dizisine çevirir
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
