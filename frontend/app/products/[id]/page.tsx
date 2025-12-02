"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import api from "../../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../../components/ui/Card";
import { Badge } from "../../components/ui/Badge";
import { ArrowLeft, Save, Package, Tag, Archive, Globe, BarChart } from "lucide-react";

interface ProductDetail {
  id: number;
  title: string;
  body_html: string;
  vendor: string;
  product_type: string;
  status: string;
  total_inventory: number;
  tags: string;
  images: { src: string }[];
  variants: {
    id: number;
    title: string;
    price: string;
    inventory_quantity: number;
    sku: string;
  }[];
}

export default function ProductDetailPage() {
  const params = useParams();
  const router = useRouter();
  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (params.id) {
      // API çağrısı: /products/{id}
      api.get(`/products/${params.id}`)
        .then((res) => {
          setProduct(res.data);
          setLoading(false);
        })
        .catch((err) => {
          console.error("Ürün detayı alınamadı", err);
          setLoading(false);
        });
    }
  }, [params.id]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
         <div className="flex flex-col items-center gap-4">
          <div className="h-10 w-10 border-4 border-amber-500/30 border-t-amber-500 rounded-full animate-spin"></div>
          <p className="text-slate-500 text-sm">Ürün bilgileri yükleniyor...</p>
        </div>
      </div>
    );
  }

  if (!product) {
    return (
       <div className="text-center py-20">
        <h2 className="text-xl font-semibold text-slate-300">Ürün Bulunamadı</h2>
        <button onClick={() => router.back()} className="mt-4 text-indigo-400 hover:text-indigo-300">
          Geri Dön
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6 animate-in fade-in zoom-in duration-300">
      
       {/* Üst Başlık ve Aksiyonlar */}
       <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800/50 pb-6">
        <div className="flex items-center gap-4">
          <button 
            onClick={() => router.back()} 
            className="p-2 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white transition-colors"
          >
            <ArrowLeft size={20} />
          </button>
          <div>
            <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
              {product.title}
              <Badge variant={product.status === 'active' ? 'success' : 'neutral'}>
                {product.status === 'active' ? 'Satışta' : 'Taslak'}
              </Badge>
            </h1>
            <p className="text-slate-400 text-sm mt-1">
               {product.vendor} • {product.product_type}
            </p>
          </div>
        </div>
        
        <div className="flex gap-2">
          <button className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-2">
            <Globe size={16} /> Mağazada Gör
          </button>
          <button className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2">
            <Save size={16} /> Kaydet
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
         {/* Sol Kolon: Ana Bilgiler */}
         <div className="lg:col-span-2 space-y-6">
            
            {/* Medya */}
            <Card>
               <CardContent className="p-6">
                 <div className="grid grid-cols-4 gap-4">
                    {/* Ana Resim */}
                    <div className="col-span-4 lg:col-span-2 h-64 bg-slate-800/50 rounded-xl border border-slate-800 overflow-hidden flex items-center justify-center group relative">
                       {product.images?.[0] ? (
                          <img src={product.images[0].src} className="h-full w-full object-contain" />
                       ) : (
                          <Package size={48} className="text-slate-600" />
                       )}
                    </div>
                    {/* Diğer Resimler */}
                    <div className="col-span-4 lg:col-span-2 grid grid-cols-2 gap-4">
                       {[1, 2, 3, 4].map((i) => (
                          <div key={i} className="h-30 bg-slate-800/30 rounded-xl border border-slate-800/50 flex items-center justify-center text-slate-600">
                             <Package size={24} className="opacity-50" />
                          </div>
                       ))}
                    </div>
                 </div>
               </CardContent>
            </Card>

            {/* Varyasyonlar */}
            <Card>
               <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Tag size={20} className="text-indigo-400" /> Varyasyonlar
                  </CardTitle>
               </CardHeader>
               <CardContent className="p-0">
                  <table className="w-full text-left text-sm">
                     <thead className="bg-slate-950/30 text-xs uppercase font-semibold text-slate-500 border-b border-slate-800/50">
                       <tr>
                         <th className="px-6 py-4">Başlık</th>
                         <th className="px-6 py-4">SKU</th>
                         <th className="px-6 py-4">Fiyat</th>
                         <th className="px-6 py-4">Stok</th>
                       </tr>
                     </thead>
                     <tbody className="divide-y divide-slate-800/50">
                        {product.variants?.map((variant) => (
                           <tr key={variant.id} className="hover:bg-slate-800/10">
                              <td className="px-6 py-4 font-medium text-slate-300">{variant.title}</td>
                              <td className="px-6 py-4 text-slate-400">{variant.sku || '-'}</td>
                              <td className="px-6 py-4 text-slate-200">{variant.price} TRY</td>
                              <td className="px-6 py-4">
                                <span className={variant.inventory_quantity > 0 ? 'text-emerald-400' : 'text-rose-400'}>
                                   {variant.inventory_quantity} adet
                                </span>
                              </td>
                           </tr>
                        ))}
                     </tbody>
                  </table>
               </CardContent>
            </Card>

         </div>

         {/* Sağ Kolon: Yan Bilgiler */}
         <div className="space-y-6">
            <Card>
               <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Archive size={20} className="text-amber-400" /> Stok Özeti
                  </CardTitle>
               </CardHeader>
               <CardContent>
                  <div className="flex items-center justify-between mb-2">
                     <span className="text-slate-400 text-sm">Toplam Stok</span>
                     <span className="text-xl font-bold text-white">{product.total_inventory}</span>
                  </div>
                  <div className="w-full bg-slate-800 rounded-full h-2">
                     <div className="bg-emerald-500 h-2 rounded-full" style={{ width: '75%' }}></div>
                  </div>
                  <p className="text-xs text-emerald-400 mt-2">Stok durumu iyi seviyede.</p>
               </CardContent>
            </Card>
            
            <Card>
               <CardHeader>
                 <CardTitle className="flex items-center gap-2">
                   <BarChart size={20} className="text-purple-400" /> Satış Performansı
                 </CardTitle>
               </CardHeader>
               <CardContent>
                  <div className="text-center py-6 text-slate-500 text-sm">
                     Bu ürün için henüz yeterli veri yok.
                  </div>
               </CardContent>
            </Card>
         </div>
      </div>
    </div>
  );
}

