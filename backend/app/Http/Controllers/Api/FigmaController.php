<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FigmaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FigmaController extends Controller
{
    protected $figmaService;

    public function __construct(FigmaService $figmaService)
    {
        $this->figmaService = $figmaService;
    }

    /**
     * Figma dosyasını analiz eder ve tasarım tokenlarını döner.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file_url' => 'required|url',
        ]);

        $fileUrl = $request->input('file_url');
        
        // URL'den File Key'i ayıkla
        // Örnek: https://www.figma.com/file/LKz123abc/My-Design... VEYA https://www.figma.com/design/LKz123abc/My-Design...
        // Hem 'file' hem de 'design' segmentlerini kabul et
        preg_match('/(file|design)\/([a-zA-Z0-9]+)/', $fileUrl, $matches);
        
        if (!isset($matches[2])) {
            return response()->json(['error' => 'Geçersiz Figma URL formatı. "figma.com/file/..." veya "figma.com/design/..." olmalıdır.'], 400);
        }

        $fileKey = $matches[2];

        try {
            // 1. Dosyayı Çek
            $fileData = $this->figmaService->getFile($fileKey);

            // 2. Tasarım Tokenlarını (Renk, Font) Ayıkla
            $tokens = $this->figmaService->extractDesignTokens($fileData);

            return response()->json([
                'success' => true,
                'file_name' => $fileData['name'] ?? 'Bilinmeyen Dosya',
                'last_modified' => $fileData['lastModified'] ?? null,
                'tokens' => $tokens,
                // 'raw_data_preview' => array_slice($fileData, 0, 2) // Debug için
            ]);

        } catch (\Exception $e) {
            Log::error('Figma Analyze Error: ' . $e->getMessage());
            return response()->json(['error' => 'Figma analizi sırasında hata oluştu: ' . $e->getMessage()], 500);
        }
    }
}

