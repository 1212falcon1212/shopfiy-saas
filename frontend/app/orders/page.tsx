"use client";

import { useEffect, useState } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../components/ui/Card";
import { Badge } from "../components/ui/Badge";
import { Search, Download, Filter, Eye, MoreHorizontal, FileText, ShoppingCart, RefreshCw } from "lucide-react";
import Link from "next/link";
import { useSettings } from "../context/SettingsContext"; // Settings context eklendi

interface Order {
  id: number;
  order_number: string;
  customer_name: string;
  total_price: string;
  currency: string;
  financial_status: string;
  created_at: string;
}

export default function OrdersPage() {
  const { selectedStore } = useSettings(); // Seçili mağazayı al
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false); // Senkronizasyon durumu
  const [searchTerm, setSearchTerm] = useState("");

  useEffect(() => {
    if (selectedStore) {
        fetchOrders();
    } else {
        setLoading(false); // Mağaza seçili değilse yüklemeyi bitir
    }
  }, [selectedStore]);

  const fetchOrders = () => {
    if (!selectedStore) return;

    setLoading(true);
    api.get(`/orders?store_id=${selectedStore.id}`) // Store ID ile filtrele
      .then((res) => {
        setOrders(res.data.data || []); // Pagination yapısında veri 'data' içindedir
        setLoading(false);
      })
      .catch((err) => {
        console.error("Siparişler yüklenirken hata:", err);
        setLoading(false);
      });
  };

  const handleSyncOrders = async () => {
    if (!selectedStore) {
        alert("Lütfen önce bir mağaza seçin.");
        return;
    }

    setSyncing(true);
    try {
        await api.post('/orders/sync', { store_id: selectedStore.id });
        alert("Sipariş senkronizasyonu başlatıldı. İşlem tamamlandığında siparişler burada listelenecektir.");
        // 3 saniye sonra listeyi yenile
        setTimeout(() => {
            fetchOrders();
        }, 3000);
    } catch (error: any) {
        console.error("Senkronizasyon hatası:", error);
        alert("Senkronizasyon başlatılamadı: " + (error.response?.data?.message || error.message));
    } finally {
        setSyncing(false);
    }
  };

  // ... (Fatura indirme ve diğer kısımlar aynı)

  const handleDownloadInvoice = (id: number, orderNumber: string) => {
    // PDF indirme işlemi (Blob olarak çekip indiriyoruz)
    api.get(`/orders/${id}/invoice`, { responseType: 'blob' })
      .then((response) => {
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `fatura-${orderNumber}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
      })
      .catch(err => console.error("Fatura indirilemedi", err));
  };

  const filteredOrders = orders.filter(order => 
    order.order_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
    order.customer_name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-8 animate-in fade-in zoom-in duration-500">
      
      {/* Üst Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
            <ShoppingCart className="text-indigo-400" /> Siparişler
          </h1>
          <p className="text-slate-400 mt-1 text-sm">Tüm siparişlerinizi yönetin ve faturalandırın.</p>
        </div>
        <div className="flex gap-2">
           <button 
             onClick={handleSyncOrders}
             disabled={syncing}
             className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-500 shadow-lg shadow-blue-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
           >
             {syncing ? (
                <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
             ) : (
                <Download size={16} />
             )}
             Shopify'dan Getir
           </button>

           <button 
             onClick={fetchOrders}
             className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-2"
           >
             <RefreshCw size={16} /> Yenile
           </button>

           <button className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-2">
             <Filter size={16} /> Filtrele
           </button>
           <button className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2">
             <Download size={16} /> Dışa Aktar
           </button>
        </div>
      </div>

      {/* Arama ve İçerik */}
      <Card>
        <CardHeader className="border-b border-slate-800/50 pb-4">
           <div className="relative max-w-sm w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
            <input 
              type="text" 
              placeholder="Sipariş no veya müşteri adı ara..." 
              className="h-10 w-full bg-slate-950/50 border border-slate-800 rounded-lg pl-10 pr-4 text-sm text-slate-300 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all placeholder:text-slate-600"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </CardHeader>

        <CardContent className="p-0">
          {loading ? (
             <div className="p-12 flex flex-col items-center justify-center text-slate-500">
                <div className="h-8 w-8 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin mb-4"></div>
                <p>Siparişler yükleniyor...</p>
             </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-950/30 text-xs uppercase font-semibold text-slate-500 border-b border-slate-800/50">
                  <tr>
                    <th className="px-6 py-4">Sipariş No</th>
                    <th className="px-6 py-4">Tarih</th>
                    <th className="px-6 py-4">Müşteri</th>
                    <th className="px-6 py-4">Tutar</th>
                    <th className="px-6 py-4">Durum</th>
                    <th className="px-6 py-4 text-right">İşlem</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/50">
                  {filteredOrders.length > 0 ? (
                    filteredOrders.map((order) => (
                      <tr key={order.id} className="group hover:bg-slate-800/20 transition-colors">
                        <td className="px-6 py-4 font-medium text-slate-200">
                          {order.order_number}
                        </td>
                        <td className="px-6 py-4 text-slate-400">
                           {/* Tarih formatlama eklenebilir */}
                           24 Kas 2023
                        </td>
                        <td className="px-6 py-4 text-slate-300">
                          <div className="flex items-center gap-2">
                            <div className="h-6 w-6 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500 border border-slate-700">
                              {order.customer_name?.charAt(0) || "-"}
                            </div>
                            {order.customer_name || "Misafir"}
                          </div>
                        </td>
                        <td className="px-6 py-4 text-slate-200 font-medium font-mono">
                          {order.total_price} <span className="text-xs text-slate-500">{order.currency}</span>
                        </td>
                        <td className="px-6 py-4">
                          <Badge variant={order.financial_status === 'paid' ? 'success' : 'warning'}>
                            {order.financial_status === 'paid' ? 'Ödendi' : order.financial_status}
                          </Badge>
                        </td>
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button 
                              onClick={() => handleDownloadInvoice(order.id, order.order_number)}
                              className="p-2 bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500 hover:text-white rounded-lg transition-colors tooltip-trigger"
                              title="Fatura İndir"
                            >
                              <FileText size={16} />
                            </button>
                            <Link 
                              href={`/orders/${order.id}`}
                              target="_blank"
                              className="p-2 hover:bg-slate-800 text-slate-400 hover:text-slate-200 rounded-lg transition-colors inline-flex"
                            >
                              <Eye size={16} />
                            </Link>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                        Aradığınız kriterlere uygun sipariş bulunamadı.
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
