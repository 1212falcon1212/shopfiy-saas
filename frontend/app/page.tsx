"use client";

import { useEffect, useState } from "react";
import api from "../app/src/services/api";
import { StatsGrid } from "./components/dashboard/StatsGrid";
import { RecentOrders } from "./components/dashboard/RecentOrders";
import { SalesChart } from "./components/dashboard/SalesChart";

interface DashboardData {
  stats: {
    revenue: string;
    orders: number;
    products: number;
    avg_cart: string;
    currency: string;
  };
  recent_orders: any[];
}

export default function Dashboard() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // API isteği
    api.get("/dashboard")
      .then((res) => {
        setData(res.data);
        setLoading(false);
      })
      .catch((err) => {
        console.error(err);
        setLoading(false);
        // Hata durumunda statik veri gösterimi (Test için)
        // setData(mockData); 
      });
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full min-h-[400px]">
        <div className="flex flex-col items-center gap-4">
          <div className="h-10 w-10 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
          <p className="text-slate-500 text-sm animate-pulse">Veriler güncelleniyor...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-in fade-in zoom-in duration-500">
      
      {/* Üst Kısım: Başlık ve Tarih Seçici (Gelecekte eklenebilir) */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Genel Bakış</h1>
          <p className="text-slate-400 mt-1 text-sm">Mağazanızın performans metrikleri ve son hareketleri.</p>
        </div>
        <div className="flex gap-2">
           <button className="px-4 py-2 bg-slate-800 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors">
             Son 30 Gün
           </button>
           <button className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5">
             Rapor İndir
           </button>
        </div>
      </div>

      {/* İstatistik Grid */}
      <StatsGrid data={data ? data.stats : null} />
      {/* Alt Grid: Tablo ve Grafik */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <RecentOrders orders={data?.recent_orders} />
        <SalesChart />
      </div>
    </div>
  );
}
