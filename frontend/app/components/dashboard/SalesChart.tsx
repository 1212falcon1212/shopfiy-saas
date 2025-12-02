import React from "react";
import { Card, CardHeader, CardTitle, CardContent } from "../ui/Card";

export function SalesChart() {
  // Statik veri simülasyonu (Vercel tarzı barlar)
  const data = [45, 70, 35, 60, 50, 85, 95];
  const days = ["Pzt", "Sal", "Çar", "Per", "Cum", "Cmt", "Paz"];
  const max = Math.max(...data);

  return (
    <Card className="col-span-1 h-full min-h-[400px]">
      <CardHeader>
        <div>
          <CardTitle>Haftalık Satışlar</CardTitle>
          <p className="text-sm text-slate-500 mt-1">Son 7 günlük performans özeti.</p>
        </div>
      </CardHeader>
      <CardContent className="h-[300px] flex items-end justify-between gap-2 pt-8 pb-2">
        {data.map((value, index) => (
          <div key={index} className="flex flex-col items-center gap-2 flex-1 group">
            <div className="relative w-full flex justify-center h-full items-end">
              <div 
                className="w-full max-w-[40px] bg-indigo-500/20 border border-indigo-500/30 rounded-t-lg hover:bg-indigo-500/40 transition-all duration-500 relative group-hover:shadow-[0_0_15px_rgba(99,102,241,0.3)]"
                style={{ height: `${(value / max) * 100}%` }}
              >
                {/* Tooltip */}
                <div className="absolute -top-10 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap border border-slate-700 pointer-events-none z-10">
                  ₺{value * 100}
                </div>
              </div>
            </div>
            <span className="text-xs text-slate-500 font-medium">{days[index]}</span>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

