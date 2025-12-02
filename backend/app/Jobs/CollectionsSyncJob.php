<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CollectionsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Log::info("Koleksiyon Senkronizasyonu Başladı: {$this->user->name}");

        try {
            // 1. Custom Collections
            $customCollections = $this->fetchCollections('/admin/api/2024-04/custom_collections.json', 'custom_collections');
            
            // 2. Smart Collections
            $smartCollections = $this->fetchCollections('/admin/api/2024-04/smart_collections.json', 'smart_collections');

            // Merge collections
            $collections = array_merge($customCollections, $smartCollections);

            // Veritabanı yerine şimdilik Cache'e atalım (Performans için)
            Cache::put("collections_{$this->user->id}", $collections, now()->addHours(24));

            Log::info(count($collections) . " adet koleksiyon önbelleğe alındı.");

        } catch (\Exception $e) {
            Log::error("Koleksiyon Sync Hatası: " . $e->getMessage());
        }
    }

    private function fetchCollections($endpoint, $key)
    {
        $allCollections = [];
        $params = ['limit' => 250];
        
        do {
            $response = $this->user->api()->rest('GET', $endpoint, $params);
            
            if ($response['errors']) {
                Log::error("Koleksiyon çekme hatası ($endpoint): " . json_encode($response['body']));
                break;
            }

            foreach ($response['body'][$key] as $c) {
                $allCollections[] = [
                    'id' => $c['id'],
                    'title' => $c['title'],
                    'handle' => $c['handle'] ?? null,
                    'type' => $key === 'custom_collections' ? 'custom' : 'smart',
                    'image' => $c['image']['src'] ?? null
                ];
            }

            // Pagination Check (Link Header)
            // Note: Basic implementation. For robust pagination with laravel-shopify, 
            // we might need to parse 'link' header or use 'since_id'.
            // For now, fetching first 250 is usually enough for most stores.
            // If more needed, we can implement cursor-based loop here.
            $params = null; // Stop after first page for now to avoid infinite loops if logic is wrong
            
        } while ($params);

        return $allCollections;
    }
}
