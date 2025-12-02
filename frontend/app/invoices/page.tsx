"use client";

import { useState, useEffect } from "react";
import api from "../src/services/api";
import { Card, CardContent, CardHeader, CardTitle } from "../components/ui/Card";
import { FileText, CheckCircle, AlertCircle, Play, Eye, RefreshCw, Search } from "lucide-react";

interface Order {
  id: number;
  order_number: string;
  customer_name: string;
  total_price: string;
  currency: string;
  created_at: string;
  invoice_status: 'pending' | 'processing' | 'completed' | 'failed';
  invoice_number?: string;
  invoice_url?: string;
  invoice_error?: string;
}

export default function InvoicesPage() {
  const [activeTab, setActiveTab] = useState<'pending' | 'completed'>('pending');
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [processingId, setProcessingId] = useState<number | null>(null);

  // Siparişleri Çek
  const fetchOrders = async () => {
    setLoading(true);
    try {
      const response = await api.get("/orders");
      // Backend'den gelen veriyi state'e at (Pagination varsa response.data.data olabilir)
      // Şimdilik direkt response.data olarak varsayıyoruz, backend yapına göre gerekirse düzeltiriz.
      setOrders(Array.isArray(response.data) ? response.data : response.data.data || []);
    } catch (error) {
      console.error("Siparişler yüklenirken hata:", error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, []);

  // Fatura Kesme İsteği
  const handleCreateInvoice = async (orderId: number) => {
    setProcessingId(orderId);
    try {
      await api.post(`/orders/${orderId}/create-invoice`);
      // İşlem başarılı mesajı (Alert yerine daha şık bir toast kullanılabilir)
      alert("Fatura oluşturma işlemi başlatıldı! (Birkaç saniye sürebilir)");
      
      // Listeyi yenile
      await fetchOrders();
    } catch (error: any) {
      alert("Hata: " + (error.response?.data?.message || "Fatura kesilemedi"));
    } finally {
      setProcessingId(null);
    }
  };

  // Tablara göre filtreleme
  const pendingOrders = orders.filter(o => o.invoice_status !== 'completed');
  const completedOrders = orders.filter(o => o.invoice_status === 'completed');

  const currentList = activeTab === 'pending' ? pendingOrders : completedOrders;

  return (
    <div className="space-y-6 animate-in fade-in zoom-in duration-500">
      
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Fatura Yönetimi</h1>
          <p className="text-slate-400 mt-1 text-sm">Siparişlerinizi faturalandırın ve yönetin.</p>
        </div>
        <button 
          onClick={fetchOrders}
          className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors"
          title="Listeyi Yenile"
        >
          <RefreshCw size={20} className={loading ? "animate-spin" : ""} />
        </button>
      </div>

      {/* TABS */}
      <div className="flex space-x-1 bg-slate-900/50 p-1 rounded-xl w-fit border border-slate-800">
        <button
          onClick={() => setActiveTab('pending')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
            activeTab === 'pending' 
              ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' 
              : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
          }`}
        >
          Fatura Bekleyenler ({pendingOrders.length})
        </button>
        <button
          onClick={() => setActiveTab('completed')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
            activeTab === 'completed' 
              ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20' 
              : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
          }`}
        >
          Tamamlananlar ({completedOrders.length})
        </button>
      </div>

      {/* TABLO KARTI */}
      <Card className="bg-[#0B1120] border-slate-800">
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 text-xs uppercase tracking-wider bg-slate-900/30">
                  <th className="p-4 font-medium">Sipariş No</th>
                  <th className="p-4 font-medium">Müşteri</th>
                  <th className="p-4 font-medium">Tarih</th>
                  <th className="p-4 font-medium">Tutar</th>
                  <th className="p-4 font-medium">Durum</th>
                  <th className="p-4 font-medium text-right">İşlem</th>
                </tr>
              </thead>
              <tbody className="text-sm divide-y divide-slate-800/50">
                {currentList.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="p-8 text-center text-slate-500">
                      Kayıt bulunamadı.
                    </td>
                  </tr>
                ) : (
                  currentList.map((order) => (
                    <tr key={order.id} className="group hover:bg-slate-800/30 transition-colors">
                      <td className="p-4 font-medium text-white">{order.order_number}</td>
                      <td className="p-4 text-slate-300">{order.customer_name}</td>
                      <td className="p-4 text-slate-400">
                        {new Date(order.created_at).toLocaleDateString('tr-TR')}
                      </td>
                      <td className="p-4 text-slate-300">
                        {order.total_price} {order.currency}
                      </td>
                      <td className="p-4">
                        {/* DURUM ROZETLERİ */}
                        {order.invoice_status === 'pending' && (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/10">
                            <AlertCircle size={12} /> Bekliyor
                          </span>
                        )}
                        {order.invoice_status === 'processing' && (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/10 animate-pulse">
                            <RefreshCw size={12} className="animate-spin" /> İşleniyor
                          </span>
                        )}
                        {order.invoice_status === 'completed' && (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/10">
                            <CheckCircle size={12} /> Kesildi
                          </span>
                        )}
                        {order.invoice_status === 'failed' && (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/10" title={order.invoice_error}>
                            <AlertCircle size={12} /> Hata
                          </span>
                        )}
                      </td>
                      <td className="p-4 text-right">
                        {activeTab === 'pending' ? (
                          <button
                            onClick={() => handleCreateInvoice(order.id)}
                            disabled={processingId === order.id || order.invoice_status === 'processing'}
                            className="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-600/50 text-white text-xs font-medium rounded-lg transition-colors shadow-lg shadow-indigo-500/20"
                          >
                            {processingId === order.id ? (
                               <RefreshCw size={14} className="animate-spin" />
                            ) : (
                               <Play size={14} fill="currentColor" />
                            )}
                            Fatura Kes
                          </button>
                        ) : (
                          <button
                            onClick={() => order.invoice_url && window.open(order.invoice_url, '_blank')}
                            disabled={!order.invoice_url}
                            className="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 disabled:opacity-50 text-white text-xs font-medium rounded-lg transition-colors"
                          >
                            <Eye size={14} /> Görüntüle
                          </button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}