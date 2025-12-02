<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Dosya Yükleme (Resim)
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB
        ]);

        if ($request->file('file')) {
            $file = $request->file('file');
            
            // Dosyayı 'public/uploads' klasörüne kaydet
            // php artisan storage:link komutunu çalıştırmayı unutma!
            $path = $file->store('uploads', 'public');
            
            // Tam URL oluştur
            $url = asset('storage/' . $path);

            return response()->json([
                'message' => 'Dosya yüklendi',
                'url' => $url,
                'path' => $path
            ]);
        }

        return response()->json(['error' => 'Dosya yüklenemedi'], 400);
    }
}

