import React from "react";
import { TrendingUp, TrendingDown, DollarSign, ShoppingBag, Users, Activity } from "lucide-react";
import { Card, CardContent } from "../ui/Card";

interface StatsProps {
  data: {
    revenue: string;
    orders: number;
    products: number;
    avg_cart: string;
    currency: string;
  } | null;
}

export function StatsGrid({ data }: StatsProps) {
  // Veri yoksa varsayılan değerler (Skeleton veya 0)
  const safeData = data || {
    revenue: "0.00",
    orders: 0,
    products: 0,
    avg_cart: "0.00",
    currency: "TRY"
  };

  const stats = [
    {
      title: "Toplam Ciro",
      value: `${safeData.revenue} ${safeData.currency}`,
      change: "+12.5%",
      trend: "up",
      icon: DollarSign,
      color: "indigo",
    },
    {
      title: "Toplam Sipariş",
      value: safeData.orders,
      change: "+8.2%",
      trend: "up",
      icon: ShoppingBag,
      color: "emerald",
    },
    {
      title: "Aktif Ürünler",
      value: safeData.products,
      change: "-1.5%",
      trend: "down",
      icon: Activity,
      color: "amber",
    },
    {
      title: "Ort. Sepet Tutarı",
      value: `${safeData.avg_cart} ${safeData.currency}`,
      change: "+4.3%",
      trend: "up",
      icon: Users,
      color: "rose",
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {stats.map((stat, index) => (
        <Card key={index} className="relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
          {/* Arka plan ikonu */}
          <div className={`absolute -top-2 -right-2 p-4 opacity-5 group-hover:opacity-10 transition-opacity text-${stat.color}-500`}>
             {/* Not: Tailwind dinamik class'ları bazen kaçırabilir, bu yüzden statik renkler daha güvenlidir, ancak v4 ile daha esnek. */}
            <stat.icon size={96} strokeWidth={1} />
          </div>
          
          <CardContent>
            <div className="flex justify-between items-start mb-4">
              <div className={`p-3 rounded-2xl bg-${stat.color}-500/10 text-${stat.color}-400 ring-1 ring-${stat.color}-500/20`}>
                <stat.icon size={22} />
              </div>
              {stat.change && (
                <div className={`flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full ${
                  stat.trend === 'up' 
                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/10' 
                    : 'bg-rose-500/10 text-rose-400 border border-rose-500/10'
                }`}>
                  {stat.trend === 'up' ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
                  {stat.change}
                </div>
              )}
            </div>
            
            <div className="space-y-1 relative z-10">
              <h4 className="text-slate-400 text-sm font-medium">{stat.title}</h4>
              <p className="text-2xl font-bold text-slate-100">{stat.value}</p>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
