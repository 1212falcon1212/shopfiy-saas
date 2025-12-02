"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import api from "../../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../../components/ui/Card";
import { Badge } from "../../components/ui/Badge";
import { ArrowLeft, User, MapPin, CreditCard, Package, Truck, Download, Calendar } from "lucide-react";

interface OrderDetail {
  id: number;
  order_number: string;
  customer_name: string;
  email: string;
  total_price: string;
  currency: string;
  financial_status: string;
  fulfillment_status: string | null;
  created_at: string;
  line_items: {
    id: number;
    title: string;
    quantity: number;
    price: string;
    sku: string | null;
  }[];
  shipping_address: {
    address1: string;
    city: string;
    country: string;
    zip: string;
  } | null;
}

export default function OrderDetailPage() {
  const params = useParams();
  const router = useRouter();
  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (params.id) {
      // API çağrısı: /orders/{id}
      // Şimdilik mock veri ile veya backend endpoint'i varsa oradan
      api.get(`/orders/${params.id}`)
        .then((res) => {
          setOrder(res.data);
          setLoading(false);
        })
        .catch((err) => {
          console.error("Sipariş detayı alınamadı", err);
          setLoading(false);
        });
    }
  }, [params.id]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="flex flex-col items-center gap-4">
          <div className="h-10 w-10 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
          <p className="text-slate-500 text-sm">Sipariş detayları yükleniyor...</p>
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="text-center py-20">
        <h2 className="text-xl font-semibold text-slate-300">Sipariş Bulunamadı</h2>
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
              {order.order_number}
              <Badge variant={order.financial_status === 'paid' ? 'success' : 'warning'}>
                {order.financial_status === 'paid' ? 'Ödendi' : order.financial_status}
              </Badge>
            </h1>
            <p className="text-slate-400 text-sm flex items-center gap-2 mt-1">
              <Calendar size={14} /> 
              {new Date(order.created_at).toLocaleDateString('tr-TR', { 
                day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' 
              })}
            </p>
          </div>
        </div>
        
        <div className="flex gap-2">
          <button className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors flex items-center gap-2">
            <Truck size={16} /> Kargo Takip
          </button>
          <button className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2">
            <Download size={16} /> Fatura İndir
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Sol Kolon: Ürünler ve Ödeme Detayı */}
        <div className="lg:col-span-2 space-y-6">
          {/* Ürün Listesi */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Package size={20} className="text-indigo-400" /> Sipariş Kalemleri
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              <div className="divide-y divide-slate-800/50">
                {order.line_items?.map((item) => (
                  <div key={item.id} className="p-6 flex items-center justify-between hover:bg-slate-800/10 transition-colors">
                    <div className="flex items-center gap-4">
                      <div className="h-12 w-12 bg-slate-800 rounded-lg border border-slate-700 flex items-center justify-center text-slate-500">
                         {/* Ürün resmi yoksa ikon */}
                         <Package size={20} />
                      </div>
                      <div>
                        <p className="text-slate-200 font-medium">{item.title}</p>
                        <p className="text-xs text-slate-500 mt-0.5">SKU: {item.sku || '-'}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-slate-200 font-medium">{item.price} {order.currency}</p>
                      <p className="text-xs text-slate-500">x {item.quantity}</p>
                    </div>
                  </div>
                ))}
              </div>
              
              {/* Toplam Özeti */}
              <div className="bg-slate-950/30 p-6 border-t border-slate-800/50 space-y-2">
                <div className="flex justify-between text-sm text-slate-400">
                  <span>Ara Toplam</span>
                  <span>{order.total_price} {order.currency}</span>
                </div>
                <div className="flex justify-between text-sm text-slate-400">
                  <span>Kargo</span>
                  <span>0.00 {order.currency}</span>
                </div>
                <div className="flex justify-between text-lg font-bold text-white pt-2 border-t border-slate-800/50 mt-2">
                  <span>Genel Toplam</span>
                  <span>{order.total_price} {order.currency}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Ödeme Geçmişi (Opsiyonel) */}
          <Card>
             <CardHeader>
               <CardTitle className="flex items-center gap-2">
                 <CreditCard size={20} className="text-emerald-400" /> Ödeme Bilgileri
               </CardTitle>
             </CardHeader>
             <CardContent>
                <div className="flex items-center justify-between p-4 bg-emerald-500/5 border border-emerald-500/10 rounded-xl">
                   <div className="flex items-center gap-3">
                      <div className="p-2 bg-emerald-500/10 rounded-full text-emerald-400">
                        <CreditCard size={18} />
                      </div>
                      <div>
                        <p className="text-sm font-medium text-emerald-100">Ödeme Başarılı</p>
                        <p className="text-xs text-emerald-400/60">Shopify Payments • {new Date(order.created_at).toLocaleDateString()}</p>
                      </div>
                   </div>
                   <span className="text-sm font-bold text-emerald-400">{order.total_price} {order.currency}</span>
                </div>
             </CardContent>
          </Card>
        </div>

        {/* Sağ Kolon: Müşteri ve Kargo */}
        <div className="space-y-6">
          
          {/* Müşteri Kartı */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <User size={20} className="text-amber-400" /> Müşteri
              </CardTitle>
            </CardHeader>
            <CardContent>
               <div className="flex items-center gap-3 mb-4">
                 <div className="h-10 w-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 font-bold border border-slate-700">
                   {order.customer_name?.charAt(0)}
                 </div>
                 <div>
                   <p className="text-slate-200 font-medium">{order.customer_name}</p>
                   <p className="text-sm text-indigo-400 cursor-pointer hover:underline">12 Sipariş</p>
                 </div>
               </div>
               
               <div className="space-y-3 pt-4 border-t border-slate-800/50">
                  <div>
                    <p className="text-xs uppercase text-slate-500 font-semibold mb-1">İletişim</p>
                    <p className="text-sm text-slate-300">{order.email || 'E-posta yok'}</p>
                  </div>
               </div>
            </CardContent>
          </Card>

          {/* Kargo Adresi */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <MapPin size={20} className="text-rose-400" /> Teslimat Adresi
              </CardTitle>
            </CardHeader>
            <CardContent>
              {order.shipping_address ? (
                <div className="text-sm text-slate-300 leading-relaxed">
                  <p>{order.shipping_address.address1}</p>
                  <p>{order.shipping_address.zip} {order.shipping_address.city}</p>
                  <p>{order.shipping_address.country}</p>
                </div>
              ) : (
                <p className="text-sm text-slate-500 italic">Adres bilgisi mevcut değil.</p>
              )}
            </CardContent>
          </Card>

        </div>
      </div>
    </div>
  );
}

