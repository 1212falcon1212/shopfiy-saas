<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use App\Models\User;
use App\Jobs\ThemeInstallJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ThemeController extends Controller
{
    /**
     * Admin kontrolü yapar.
     */
    protected function checkAdmin()
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Bu işlem için admin yetkisi gereklidir.');
        }
    }
    /**
     * Sistemdeki mevcut temaları listeler.
     */
    public function index()
    {
        // Veritabanından temaları çek
        // Eğer henüz Theme modeli veya verisi yoksa manuel bir liste dönelim test için
        $themes = Theme::all();

        if ($themes->isEmpty()) {
             // Fallback: Demo Temayı manuel ekleyelim (Eğer DB boşsa)
             // Gerçek hayatta bunları Seeder ile ekleriz.
             return response()->json([
                 [
                     'id' => 999,
                     'name' => 'Modern Start (Demo)',
                     'description' => 'Minimalist ve hızlı bir başlangıç teması.',
                     'preview_image' => 'https://cdn.shopify.com/s/files/1/0533/2089/files/placeholder-images-collection-1_large.png',
                     'is_active' => true,
                     'folder_path' => 'themes/demo-theme' // storage/app/themes/demo-theme
                 ]
             ]);
        }

        return response()->json($themes);
    }

    /**
     * Temayı kullanıcının mağazasına kurar.
     */
    public function install(Request $request, $id)
    {
        $user = Auth::user(); // Şu anki oturum açmış mağaza sahibi
        
        if (!$user) {
             // Token bazlı auth kullanıyorsak ve middleware'den geçmediyse
             // Ama middleware('auth:sanctum') varsa buraya user gelir.
             // Test ortamında bazen User::first() kullanırız.
             $user = User::first(); 
        }

        // Temayı Bul
        if ($id == 999) {
            // Demo Tema Mock Obj
            $theme = new Theme();
            $theme->name = 'Modern Start (Demo)';
            $theme->folder_path = 'themes/demo-theme';
        } else {
            $theme = Theme::findOrFail($id);
        }

        // Job'ı Tetikle
        ThemeInstallJob::dispatch($user, $theme);

        return response()->json([
            'success' => true,
            'message' => 'Tema kurulumu arka planda başlatıldı. İşlem birkaç dakika sürebilir.'
        ]);
    }

    /**
     * Admin: Yeni tema yükler (ZIP dosyası).
     */
    public function upload(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'zip_file' => 'required|file|mimes:zip|max:10240', // Max 10MB
        ]);

        try {
            // ZIP dosyasını geçici olarak kaydet
            $zipFile = $request->file('zip_file');
            $tempPath = $zipFile->storeAs('temp', 'theme-' . time() . '.zip');

            // ZIP'i aç
            $zip = new ZipArchive();
            $zipPath = storage_path('app/' . $tempPath);
            
            if ($zip->open($zipPath) !== TRUE) {
                return response()->json(['error' => 'ZIP dosyası açılamadı.'], 400);
            }

            // Tema klasörü oluştur
            $themeSlug = \Illuminate\Support\Str::slug($request->name);
            $themeFolder = 'themes/' . $themeSlug;
            $themePath = storage_path('app/' . $themeFolder);
            
            File::makeDirectory($themePath, 0755, true, true);

            // ZIP içeriğini çıkar
            $zip->extractTo($themePath);
            $zip->close();

            // Geçici ZIP'i sil
            Storage::delete($tempPath);

            // Veritabanına kaydet
            $theme = Theme::create([
                'name' => $request->name,
                'slug' => $themeSlug,
                'description' => $request->description ?? '',
                'folder_path' => $themeFolder,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tema başarıyla yüklendi.',
                'theme' => $theme
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Tema yüklenirken hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}

