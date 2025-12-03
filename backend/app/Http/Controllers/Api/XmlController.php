<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\XmlParserService;
use App\Models\XmlIntegration;
use App\Jobs\XmlImportJob;

class XmlController extends Controller
{
    protected $xmlService;

    public function __construct(XmlParserService $xmlService)
    {
        $this->xmlService = $xmlService;
    }

    // 0. Entegrasyonları Listele
    public function index(Request $request)
    {
        // Şimdilik test kullanıcısı (user_id = 1)
        // Auth eklendiğinde: auth()->user()->xmlIntegrations
        $userId = 1;

        $integrations = XmlIntegration::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($integrations);
    }

    // Tekil Entegrasyon Getir (Edit için)
    public function show($id)
    {
        $integration = XmlIntegration::findOrFail($id);
        return response()->json($integration);
    }

    // 1. XML Önizleme
    public function preview(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $result = $this->xmlService->preview($request->url);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json($result);
    }

    // 2. Entegrasyonu Kaydetme
    public function store(Request $request)
    {
        $validated = $request->validate([
            'xml_url' => 'required|url',
            'field_mapping' => 'required|array',
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $integration = XmlIntegration::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'xml_url' => $validated['xml_url']
            ],
            [
                'field_mapping' => $validated['field_mapping'],
                'is_active' => true
            ]
        );

        return response()->json([
            'message' => 'Entegrasyon başarıyla kaydedildi!',
            'data' => $integration
        ], 201);
    }

    // Entegrasyon Güncelleme
    public function update(Request $request, $id)
    {
        $integration = XmlIntegration::findOrFail($id);

        $validated = $request->validate([
            'xml_url' => 'required|url',
            'field_mapping' => 'required|array',
        ]);

        $integration->update([
            'xml_url' => $validated['xml_url'],
            'field_mapping' => $validated['field_mapping']
        ]);

        return response()->json([
            'message' => 'Entegrasyon güncellendi.',
            'data' => $integration
        ]);
    }

    // 3. Entegrasyonu Silme
    public function destroy($id)
    {
        $integration = XmlIntegration::findOrFail($id);

        // Güvenlik: Kullanıcı kendi entegrasyonunu mu siliyor?
        // if ($integration->user_id !== auth()->id()) abort(403);

        $integration->delete();

        return response()->json(['message' => 'Entegrasyon silindi.']);
    }

    // 4. Manuel Senkronizasyon Başlatma
    public function sync($id)
    {
        $integration = XmlIntegration::findOrFail($id);

        XmlImportJob::dispatch($integration);

        return response()->json(['message' => 'Senkronizasyon işlemi kuyruğa alındı.']);
    }
}
