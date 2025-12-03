<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id', // Yeni eklendi
        'shopify_order_id',
        'order_number',
        'customer_name',
        'customer_email',
        'shipping_address',
        'total_price',
        'currency',
        'financial_status',
        'fulfillment_status',
        'line_items',
        'shipping_lines'
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'line_items' => 'array',
        'total_price' => 'decimal:2',
        'shipping_lines' => 'array'
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
