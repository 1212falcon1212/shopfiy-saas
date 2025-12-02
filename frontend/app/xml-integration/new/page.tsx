"use client";

import { useState } from "react";
import api from "../../src/services/api";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardContent } from "../../components/ui/Card";
import { ArrowLeft, Check, UploadCloud, Link as LinkIcon, AlertCircle } from "lucide-react";

export default function NewXmlIntegrationPage() {
  const router = useRouter();
  const [url, setUrl] = useState("");
  const [loading, setLoading] = useState(false);
  const [previewData, setPreviewData] = useState<any>(null);
  const [error, setError] = useState("");

  const [mapping, setMapping] = useState({
    title: "",
    price: "",
    sku: "",
    stock: "",
    image: "",
    category: "" // YENİ
  });

  const handlePreview = async () => {
    if (!url) return;
    setLoading(true);
    setError("");
    setPreviewData(null);

    try {
      const response = await api.post("/xml/preview", { url });
      setPreviewData(response.data);
    } catch (err: any) {
      setError(err.response?.data?.message || "XML analiz edilirken bir hata oluştu. Lütfen URL'i kontrol edin.");
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (!mapping.title || !mapping.price) {
      alert("Lütfen en azından Ürün Adı (Title) ve Fiyat (Price) alanlarını eşleştirin.");
      return;
    }

    setLoading(true);

    try {
      const payload = {
        xml_url: url,
        field_mapping: mapping,
        user_id: 1 
      };

      await api.post("/xml/store", payload);
      router.push('/xml-integration'); // Listeye dön
      
    } catch (err: any) {
      console.error(err);
      alert("Hata: " + (err.response?.data?.message || "Kaydedilemedi."));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto animate-in fade-in zoom-in duration-300 space-y-6">
      
      <div className="flex items-center gap-4 mb-6">
        <button 
           onClick={() => router.back()} 
           className="p-2 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white transition-colors"
        >
           <ArrowLeft size={20} />
        </button>
        <h1 className="text-2xl font-bold text-white tracking-tight">Yeni XML Entegrasyonu</h1>
      </div>

      {/* Adım 1: URL Girişi */}
      <Card>
         <CardHeader>
           <CardTitle className="flex items-center gap-2">
              <LinkIcon className="text-indigo-400" size={20} /> XML Kaynağı
           </CardTitle>
         </CardHeader>
         <CardContent>
            <div className="flex gap-3">
              <input
                type="text"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://tedarikci.com/urunler.xml"
                className="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 outline-none placeholder:text-slate-600 transition-all"
              />
              <button
                onClick={handlePreview}
                disabled={loading || !url}
                className="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-all shadow-lg shadow-indigo-500/20 flex items-center gap-2"
              >
                {loading ? <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <UploadCloud size={18} />}
                {loading ? "Analiz..." : "Analiz Et"}
              </button>
            </div>
            {error && (
               <div className="mt-4 p-3 bg-rose-500/10 border border-rose-500/20 rounded-lg flex items-center gap-3 text-rose-400 text-sm">
                  <AlertCircle size={18} />
                  {error}
               </div>
            )}
         </CardContent>
      </Card>

      {/* Adım 2: Eşleştirme (Mapping) */}
      {previewData && (
        <Card className="animate-in slide-in-from-bottom-4 duration-500">
           <CardHeader className="border-b border-slate-800/50">
             <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                   <Check className="text-emerald-400" size={20} /> Alan Eşleştirme
                </CardTitle>
                <div className="text-sm text-slate-400">
                   Bulunan Ürün Sayısı: <span className="text-white font-bold">{previewData.count}</span>
                </div>
             </div>
           </CardHeader>
           <CardContent className="space-y-6 pt-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                 
                 {/* Eşleştirme Formu */}
                 <div className="space-y-4">
                    {[
                      { key: 'title', label: 'Ürün Adı (Zorunlu)', req: true },
                      { key: 'price', label: 'Fiyat (Zorunlu)', req: true },
                      { key: 'sku', label: 'Stok Kodu (SKU)', req: false },
                      { key: 'stock', label: 'Stok Adedi', req: false },
                      { key: 'image', label: 'Görsel URL', req: false },
                      { key: 'category', label: 'Kategori / Koleksiyon', req: false } // YENİ
                    ].map((field) => (
                       <div key={field.key}>
                          <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">
                             {field.label}
                          </label>
                          <select
                            value={mapping[field.key as keyof typeof mapping]}
                            onChange={(e) => setMapping({ ...mapping, [field.key]: e.target.value })}
                            className={`w-full bg-slate-950 border rounded-lg px-3 py-2.5 text-slate-200 outline-none focus:ring-1 transition-all ${
                               field.req && !mapping[field.key as keyof typeof mapping] 
                               ? 'border-rose-500/30 focus:border-rose-500 focus:ring-rose-500/20' 
                               : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500/20'
                            }`}
                          >
                             <option value="">Seçiniz...</option>
                             {previewData.fields.map((f: string) => (
                                <option key={f} value={f}>{f}</option>
                             ))}
                          </select>
                       </div>
                    ))}
                 </div>

                 {/* Önizleme Kartı */}
                 <div className="bg-slate-950 rounded-xl p-6 border border-slate-800 h-fit">
                    <h3 className="text-sm font-semibold text-slate-300 mb-4 flex items-center gap-2">
                       <UploadCloud size={16} /> Örnek Veri Önizleme
                    </h3>
                    
                    {/* Örnek veri gösterimi - İlk ürünün ham verisinden mapping'e göre seçilenleri göster */}
                    <div className="space-y-3 text-sm">
                       <div className="p-3 bg-slate-900 rounded-lg border border-slate-800">
                          <span className="text-slate-500 text-xs block mb-1">Ürün Adı</span>
                          <span className="text-slate-200 font-medium">
                             {mapping.title ? previewData.sample_item[mapping.title] || '-' : 'Seçilmedi'}
                          </span>
                       </div>
                       <div className="flex gap-3">
                          <div className="flex-1 p-3 bg-slate-900 rounded-lg border border-slate-800">
                             <span className="text-slate-500 text-xs block mb-1">Fiyat</span>
                             <span className="text-emerald-400 font-medium">
                                {mapping.price ? previewData.sample_item[mapping.price] || '-' : 'Seçilmedi'}
                             </span>
                          </div>
                          <div className="flex-1 p-3 bg-slate-900 rounded-lg border border-slate-800">
                             <span className="text-slate-500 text-xs block mb-1">Stok</span>
                             <span className="text-slate-200">
                                {mapping.stock ? previewData.sample_item[mapping.stock] || '-' : 'Seçilmedi'}
                             </span>
                          </div>
                          <div className="flex-1 p-3 bg-slate-900 rounded-lg border border-slate-800">
                             <span className="text-slate-500 text-xs block mb-1">Kategori</span>
                             <span className="text-slate-200">
                                {mapping.category ? previewData.sample_item[mapping.category] || '-' : 'Seçilmedi'}
                             </span>
                          </div>
                       </div>
                    </div>

                 </div>

              </div>

              <div className="pt-6 border-t border-slate-800/50 flex justify-end">
                 <button
                    onClick={handleSave}
                    className="bg-emerald-600 text-white px-8 py-3 rounded-lg hover:bg-emerald-500 font-bold transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2"
                 >
                    <Check size={18} /> Kaydet ve Entegrasyonu Başlat
                 </button>
              </div>
           </CardContent>
        </Card>
      )}

    </div>
  );
}

