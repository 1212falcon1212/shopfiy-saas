"use client";

import { useEffect, useState } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardContent } from "../components/ui/Card";
import { Badge } from "../components/ui/Badge";
import { Search, Plus, RefreshCw, Edit, Trash2, Package, UploadCloud, CheckSquare, Square } from "lucide-react";
import Link from "next/link";

interface Product {
  id: number;
  title: string;
  vendor: string;
  product_type: string;
  status: string;
  total_inventory: number;
  shopify_product_id: string | null; // Shopify ID var mı?
  images: { src: string }[];
}

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState("");
  
  // Çoklu seçim state'i
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  useEffect(() => {
    fetchProducts();
  }, []);

  const fetchProducts = () => {
    setLoading(true);
    api.get("/products?limit=50")
      .then((res) => {
         setProducts(res.data.data || []);
         setLoading(false);
      })
      .catch((err) => {
        console.error("Ürünler yüklenirken hata:", err);
        setLoading(false);
      });
  };

  const filteredProducts = products.filter(product => 
    product.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
    product.vendor.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Seçim işlemleri
  const toggleSelect = (id: number) => {
    setSelectedIds(prev => 
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    );
  };

  const toggleSelectAll = () => {
    if (selectedIds.length === filteredProducts.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(filteredProducts.map(p => p.id));
    }
  };

  const handleBulkPush = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`${selectedIds.length} adet ürünü Shopify mağazasına göndermek istiyor musunuz?`)) return;

    try {
      await api.post("/products/bulk-push", { product_ids: selectedIds });
      alert("Seçilen ürünler gönderim kuyruğuna eklendi! 🚀");
      setSelectedIds([]); // Seçimi temizle
    } catch (err: any) {
      alert("Hata oluştu: " + err.message);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in zoom-in duration-500">
      
      {/* Üst Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
            <Package className="text-amber-400" /> Ürünler
          </h1>
          <p className="text-slate-400 mt-1 text-sm">Mağazanızdaki tüm ürünleri buradan yönetin.</p>
        </div>
        <div className="flex gap-2">
           {selectedIds.length > 0 && (
             <button 
               onClick={handleBulkPush}
               className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2 animate-in fade-in slide-in-from-right-4"
             >
               <UploadCloud size={16} /> Seçilenleri Shopify'a Gönder ({selectedIds.length})
             </button>
           )}
           
           <button 
             onClick={fetchProducts}
             className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-2"
           >
             <RefreshCw size={16} /> Yenile
           </button>
           <Link 
             href="/products/new"
             className="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-500 shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2"
           >
             <Plus size={16} /> Yeni Ürün
           </Link>
        </div>
      </div>

      {/* Arama ve Liste */}
      <Card>
        <CardHeader className="border-b border-slate-800/50 pb-4">
           <div className="flex flex-col sm:flex-row gap-4 w-full justify-between">
             <div className="relative max-w-sm w-full">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
              <input 
                type="text" 
                placeholder="Ürün adı, etiket veya satıcı ara..." 
                className="h-10 w-full bg-slate-950/50 border border-slate-800 rounded-lg pl-10 pr-4 text-sm text-slate-300 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all placeholder:text-slate-600"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <div className="flex gap-2">
               {/* Filtreler eklenebilir */}
            </div>
           </div>
        </CardHeader>

        <CardContent className="p-0">
          {loading ? (
             <div className="p-12 flex flex-col items-center justify-center text-slate-500">
                <div className="h-8 w-8 border-4 border-amber-500/30 border-t-amber-500 rounded-full animate-spin mb-4"></div>
                <p>Ürünler listeleniyor...</p>
             </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-950/30 text-xs uppercase font-semibold text-slate-500 border-b border-slate-800/50">
                  <tr>
                    <th className="px-6 py-4 w-10">
                      <button onClick={toggleSelectAll} className="flex items-center text-slate-500 hover:text-white transition-colors">
                        {selectedIds.length > 0 && selectedIds.length === filteredProducts.length 
                          ? <CheckSquare size={18} className="text-indigo-400" /> 
                          : <Square size={18} />
                        }
                      </button>
                    </th>
                    <th className="px-6 py-4 w-16">Görsel</th>
                    <th className="px-6 py-4">Ürün Adı</th>
                    <th className="px-6 py-4">Satıcı</th>
                    <th className="px-6 py-4">Stok</th>
                    <th className="px-6 py-4">Durum</th>
                    <th className="px-6 py-4 text-right">İşlem</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/50">
                  {filteredProducts.length > 0 ? (
                    filteredProducts.map((product) => {
                      const isSelected = selectedIds.includes(product.id);
                      return (
                        <tr key={product.id} className={`group transition-colors ${isSelected ? 'bg-indigo-500/5' : 'hover:bg-slate-800/20'}`}>
                          <td className="px-6 py-4">
                            <button onClick={() => toggleSelect(product.id)} className="flex items-center text-slate-500 hover:text-white transition-colors">
                               {isSelected 
                                 ? <CheckSquare size={18} className="text-indigo-400" /> 
                                 : <Square size={18} />
                               }
                            </button>
                          </td>
                          <td className="px-6 py-4">
                            <div className="h-10 w-10 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center overflow-hidden">
                              {product.images?.[0]?.src ? (
                                <img src={product.images[0].src} alt="" className="h-full w-full object-cover" />
                              ) : (
                                <Package className="text-slate-600" size={20} />
                              )}
                            </div>
                          </td>
                          <td className="px-6 py-4 font-medium text-slate-200">
                            {product.title}
                            <div className="flex gap-2 mt-0.5">
                               <span className="text-xs text-slate-500">{product.product_type}</span>
                               {product.shopify_product_id && (
                                  <span className="text-[10px] px-1.5 py-0.5 rounded bg-[#96bf48]/10 text-[#96bf48] border border-[#96bf48]/20 flex items-center gap-1">
                                    Shopify
                                  </span>
                               )}
                            </div>
                          </td>
                          <td className="px-6 py-4 text-slate-400">
                            {product.vendor}
                          </td>
                          <td className="px-6 py-4">
                            <span className={`text-sm ${product.total_inventory > 0 ? 'text-slate-300' : 'text-rose-400'}`}>
                              {product.total_inventory} adet
                            </span>
                          </td>
                          <td className="px-6 py-4">
                            <Badge variant={product.status === 'active' ? 'success' : 'neutral'}>
                              {product.status === 'active' ? 'Satışta' : 'Taslak'}
                            </Badge>
                          </td>
                          <td className="px-6 py-4 text-right">
                            <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                              <Link 
                                href={`/products/${product.id}`}
                                target="_blank"
                                className="p-2 hover:bg-indigo-500/10 hover:text-indigo-400 text-slate-400 rounded-lg transition-colors inline-flex"
                              >
                                <Edit size={16} />
                              </Link>
                              <button className="p-2 hover:bg-rose-500/10 hover:text-rose-400 text-slate-400 rounded-lg transition-colors">
                                <Trash2 size={16} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={7} className="px-6 py-12 text-center text-slate-500">
                        {searchTerm ? 'Aradığınız kriterlere uygun ürün bulunamadı.' : 'Henüz ürün bulunmuyor.'}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
