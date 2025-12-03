<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XmlIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'xml_url',
        'field_mapping',
        'is_active',
        'sync_frequency',
        'last_synced_at'
    ];

    // JSON verisini otomatik diziye (array) çevirir
    protected $casts = [
        'field_mapping' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    // Bu entegrasyon kime ait?
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
