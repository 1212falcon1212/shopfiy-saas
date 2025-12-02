import React from "react";
import { MoreHorizontal, ArrowUpRight } from "lucide-react";
import { Card, CardHeader, CardTitle, CardContent } from "../ui/Card";
import { Badge } from "../ui/Badge";

interface Order {
  id: number;
  order_number: string;
  customer_name: string;
  total_price: string;
  currency: string;
  financial_status: string;
}

interface RecentOrdersProps {
  orders: Order[] | undefined;
}

export function RecentOrders({ orders }: RecentOrdersProps) {
  return (
    <Card className="col-span-1 lg:col-span-2 h-full">
      <CardHeader>
        <div className="flex items-center justify-between w-full">
          <div>
            <CardTitle>Son Siparişler</CardTitle>
            <p className="text-sm text-slate-500 mt-1">Mağazanızdan gelen son işlemler.</p>
          </div>
          <button className="text-xs font-medium text-indigo-400 hover:text-indigo-300 flex items-center gap-1 transition-colors">
            Tümünü Gör <ArrowUpRight size={14} />
          </button>
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead className="bg-slate-950/30 text-xs uppercase font-semibold text-slate-500 border-b border-slate-800/50">
              <tr>
                <th className="px-6 py-4">Sipariş No</th>
                <th className="px-6 py-4">Müşteri</th>
                <th className="px-6 py-4">Tutar</th>
                <th className="px-6 py-4">Durum</th>
                <th className="px-6 py-4 text-right">İşlem</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/50 text-sm">
              {orders?.map((order) => (
                <tr key={order.id} className="group hover:bg-slate-800/20 transition-colors">
                  <td className="px-6 py-4 font-medium text-slate-200">
                    {order.order_number}
                  </td>
                  <td className="px-6 py-4 text-slate-400">
                    <div className="flex items-center gap-2">
                      <div className="h-6 w-6 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-500">
                        {order.customer_name.charAt(0)}
                      </div>
                      {order.customer_name}
                    </div>
                  </td>
                  <td className="px-6 py-4 text-slate-200 font-medium">
                    {order.total_price} <span className="text-xs text-slate-500">{order.currency}</span>
                  </td>
                  <td className="px-6 py-4">
                    <Badge variant={order.financial_status === 'paid' ? 'success' : 'warning'}>
                      {order.financial_status === 'paid' ? 'Ödendi' : order.financial_status}
                    </Badge>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button className="p-1 text-slate-500 hover:text-slate-300 rounded transition-colors">
                      <MoreHorizontal size={16} />
                    </button>
                  </td>
                </tr>
              ))}
              {!orders?.length && (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-slate-500">
                    Henüz sipariş bulunmuyor.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
}

