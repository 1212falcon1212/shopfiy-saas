"use client";

import { useState, useEffect } from "react";
import api from "../../src/services/api";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardContent } from "../../components/ui/Card";
import { ImageUpload } from "../../components/ui/ImageUpload"; // YENİ
import { ArrowLeft, Save, Package, DollarSign, Tag, Layers, FileText, Plus, Trash, Folder } from "lucide-react";

interface Category {
  id: number;
  name: string;
}

export default function NewProductPage() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]); // Kategoriler
  
  const [formData, setFormData] = useState({
    title: "",
    body_html: "",
    vendor: "",
    product_type: "", // Bunu category ile aynı tutacağız
    category: "", // Yeni Kategori Alanı
    price: "",
    sku: "",
    inventory_quantity: "0",
    status: "active",
    image_url: "", 
    variants: [] as { name: string, value: string, price: string, sku: string, inventory_quantity: string }[]
  });

  // Gelişmiş Varyasyon Ekleme
  const [variantInput, setVariantInput] = useState({ name: "Beden", value: "", price: "", sku: "", inventory_quantity: "" });

  useEffect(() => {
    // Kategorileri Çek (Yerel DB)
    api.get("/categories")
      .then(res => setCategories(res.data))
      .catch(err => console.error("Kategoriler alınamadı", err));
  }, []);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleImageUpload = (url: string) => {
    setFormData(prev => ({ ...prev, image_url: url }));
  };

  const addVariant = () => {
    if (!variantInput.value || !variantInput.price) return;
    setFormData(prev => ({
      ...prev,
      variants: [...prev.variants, variantInput]
    }));
    // Sadece değeri sıfırla, isim (Beden) kalsın
    setVariantInput(prev => ({ ...prev, value: "", sku: "", inventory_quantity: "" }));
  };

  const removeVariant = (index: number) => {
    setFormData(prev => ({
      ...prev,
      variants: prev.variants.filter((_, i) => i !== index)
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await api.post("/products", formData);
      alert("Ürün başarıyla Shopify'a gönderildi ve panele eklendi! 🚀");
      router.push("/products");
    } catch (err: any) {
      console.error(err);
      alert("Hata: " + (err.response?.data?.message || "Ürün oluşturulamadı."));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto animate-in fade-in zoom-in duration-300 space-y-6 pb-20">
      
      {/* Başlık */}
      <div className="flex items-center gap-4 mb-6">
        <button 
           onClick={() => router.back()} 
           className="p-2 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white transition-colors"
        >
           <ArrowLeft size={20} />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Yeni Ürün Ekle</h1>
          <p className="text-slate-400 text-sm mt-1">Ürünü oluşturun ve anında Shopify mağazanızda yayınlayın.</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Sol Kolon: Ana Bilgiler */}
        <div className="lg:col-span-2 space-y-6">
           <Card>
              <CardHeader>
                 <CardTitle className="flex items-center gap-2">
                    <Package className="text-indigo-400" size={20} /> Ürün Bilgileri
                 </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Ürün Adı</label>
                    <input
                      required
                      name="title"
                      value={formData.title}
                      onChange={handleChange}
                      type="text"
                      placeholder="Örn: Kırmızı Kazak"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                    />
                 </div>
                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Açıklama</label>
                    <textarea
                      name="body_html"
                      value={formData.body_html}
                      onChange={handleChange}
                      rows={5}
                      placeholder="Ürün açıklaması..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all resize-none"
                    />
                 </div>
                 
                 {/* Resim Yükleme Alanı */}
                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Ürün Görseli</label>
                    <ImageUpload onUpload={handleImageUpload} />
                 </div>
              </CardContent>
           </Card>

           <Card>
              <CardHeader>
                 <CardTitle className="flex items-center gap-2">
                    <Layers className="text-amber-400" size={20} /> Stok & Varyasyon
                 </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                 
                 {/* Varyasyon Ekleme Formu */}
                 <div className="bg-slate-900/50 p-4 rounded-xl border border-slate-800 space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                       <div>
                          <label className="text-xs text-slate-400 block mb-1">Seçenek Adı</label>
                          <input 
                            value={variantInput.name}
                            onChange={(e) => setVariantInput({...variantInput, name: e.target.value})}
                            className="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-sm text-white" 
                            placeholder="Örn: Renk"
                          />
                       </div>
                       <div>
                          <label className="text-xs text-slate-400 block mb-1">Değer</label>
                          <input 
                            value={variantInput.value}
                            onChange={(e) => setVariantInput({...variantInput, value: e.target.value})}
                            className="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-sm text-white" 
                            placeholder="Örn: Kırmızı"
                          />
                       </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                       <div>
                          <label className="text-xs text-slate-400 block mb-1">Fiyat</label>
                          <input 
                            type="number"
                            value={variantInput.price}
                            onChange={(e) => setVariantInput({...variantInput, price: e.target.value})}
                            className="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-sm text-white" 
                            placeholder="0.00"
                          />
                       </div>
                       <div>
                          <label className="text-xs text-slate-400 block mb-1">Stok</label>
                          <input 
                            type="number"
                            value={variantInput.inventory_quantity}
                            onChange={(e) => setVariantInput({...variantInput, inventory_quantity: e.target.value})}
                            className="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-sm text-white" 
                            placeholder="0"
                          />
                       </div>
                       <div className="flex items-end">
                          <button 
                            type="button" 
                            onClick={addVariant}
                            className="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded py-2 text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                          >
                            <Plus size={16} /> Ekle
                          </button>
                       </div>
                    </div>
                 </div>

                 {/* Eklenen Varyasyonlar Listesi */}
                 {formData.variants.length > 0 ? (
                    <div className="space-y-2 mt-4">
                       {formData.variants.map((v, i) => (
                          <div key={i} className="flex items-center justify-between p-3 bg-slate-800/30 rounded border border-slate-800">
                             <div className="flex flex-col">
                                <span className="font-medium text-white text-sm">{v.name}: {v.value}</span>
                                <div className="text-xs text-slate-500 flex gap-3 mt-0.5">
                                   <span>{v.price} TL</span>
                                   <span>Stok: {v.inventory_quantity}</span>
                                </div>
                             </div>
                             <button type="button" onClick={() => removeVariant(i)} className="text-rose-400 hover:text-rose-300">
                                <Trash size={14} />
                             </button>
                          </div>
                       ))}
                    </div>
                 ) : (
                    <p className="text-xs text-slate-500 italic text-center py-2">Henüz varyasyon eklenmedi. Temel ürün oluşturulacak.</p>
                 )}
              </CardContent>
           </Card>
        </div>

        {/* Sağ Kolon: Organizasyon ve Temel Fiyat */}
        <div className="space-y-6">
           <Card>
              <CardHeader>
                 <CardTitle className="flex items-center gap-2">
                    <Tag className="text-emerald-400" size={20} /> Organizasyon
                 </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                 
                 {/* Kategori Seçimi (YENİ) */}
                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1 flex items-center gap-2">
                       <Folder size={12} /> Kategori
                    </label>
                    <input
                      list="category-list"
                      name="category"
                      value={formData.category}
                      onChange={handleChange}
                      placeholder="Kategori seçin veya yeni yazın..."
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all placeholder:text-slate-600"
                    />
                    <datalist id="category-list">
                       {categories.map(c => (
                          <option key={c.id} value={c.name} />
                       ))}
                    </datalist>
                    <p className="text-[10px] text-slate-500 mt-1 ml-1">Listeden seçebilir veya yeni bir kategori ismi yazabilirsiniz.</p>
                 </div>

                 {formData.variants.length === 0 && (
                    <>
                       <div>
                          <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Fiyat (TRY)</label>
                          <div className="relative">
                             <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={16} />
                             <input
                               required
                               name="price"
                               value={formData.price}
                               onChange={handleChange}
                               type="number"
                               step="0.01"
                               placeholder="0.00"
                               className="w-full bg-slate-950 border border-slate-800 rounded-lg pl-10 pr-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                             />
                          </div>
                       </div>
                       <div>
                          <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Stok Adedi</label>
                          <input
                            required
                            name="inventory_quantity"
                            value={formData.inventory_quantity}
                            onChange={handleChange}
                            type="number"
                            placeholder="0"
                            className="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                          />
                       </div>
                       <div className="h-px bg-slate-800 my-4" />
                    </>
                 )}

                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Ürün Durumu</label>
                    <select
                      name="status"
                      value={formData.status}
                      onChange={handleChange}
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                    >
                       <option value="active">Aktif (Satışta)</option>
                       <option value="draft">Taslak</option>
                       <option value="archived">Arşivlenmiş</option>
                    </select>
                 </div>
                 <div>
                    <label className="block text-xs font-medium text-slate-400 uppercase mb-1.5 ml-1">Satıcı (Marka)</label>
                    <input
                      name="vendor"
                      value={formData.vendor}
                      onChange={handleChange}
                      type="text"
                      placeholder="Örn: Nike"
                      className="w-full bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 text-slate-200 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                    />
                 </div>
              </CardContent>
           </Card>

           <button
              type="submit"
              disabled={loading}
              className="w-full bg-emerald-600 text-white px-6 py-4 rounded-xl hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed font-bold text-lg transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2"
           >
              {loading ? <div className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Save size={20} />}
              {loading ? "Gönderiliyor..." : "Kaydet ve Yayınla"}
           </button>
        </div>

      </form>
    </div>
  );
}
