<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    // Veritabanına toplu eklemeye izin verdiğimiz alanlar
    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail_url',
        'folder_path',
        'is_active',
        'price'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2'
    ];
}