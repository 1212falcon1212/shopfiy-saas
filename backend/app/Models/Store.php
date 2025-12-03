<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'access_token',
        'shop_owner',
        'email',
        'kolaysoft_username',
        'kolaysoft_password',
        'kolaysoft_vkn_tckn',
        'kolaysoft_supplier_name',
        'currency',
        'locale',
        'is_active'
    ];

    /**
     * Mağazanın sahibi olan kullanıcı.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
