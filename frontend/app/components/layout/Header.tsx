"use client";

import { Bell, Search, Settings } from "lucide-react";

export function Header() {
  return (
    <header className="h-20 flex items-center justify-between px-8 border-b border-slate-800/60 bg-[#0B1120]/80 backdrop-blur-md sticky top-0 z-30">
      
      {/* Sol: Breadcrumb veya Selamlama */}
      <div>
        <h2 className="text-slate-100 font-semibold text-lg">Hoşgeldin, Sahin 👋</h2>
        <p className="text-slate-500 text-xs mt-0.5">Bugün işler yolunda gidiyor.</p>
      </div>

      {/* Sağ: Aksiyonlar */}
      <div className="flex items-center gap-4">
        
        {/* Arama */}
        <div className="hidden md:flex items-center relative group">
          <Search className="absolute left-3 h-4 w-4 text-slate-500 group-hover:text-indigo-400 transition-colors" />
          <input 
            type="text" 
            placeholder="Sipariş veya ürün ara..." 
            className="h-10 w-64 bg-slate-900/50 border border-slate-800 rounded-full pl-10 pr-4 text-sm text-slate-300 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all placeholder:text-slate-600"
          />
        </div>

        <div className="h-6 w-px bg-slate-800 mx-2" />

        {/* Bildirimler */}
        <button className="relative p-2 text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-full transition-all">
          <Bell className="h-5 w-5" />
          <span className="absolute top-2 right-2 h-2 w-2 bg-rose-500 rounded-full border-2 border-[#0B1120]"></span>
        </button>

        {/* Ayarlar */}
        <button className="p-2 text-slate-400 hover:text-slate-100 hover:bg-slate-800 rounded-full transition-all">
          <Settings className="h-5 w-5" />
        </button>

        {/* Profil */}
        <div className="ml-2 flex items-center gap-3 pl-4 border-l border-slate-800">
           <div className="text-right hidden sm:block">
             <div className="text-sm font-medium text-slate-200">Admin User</div>
             <div className="text-xs text-indigo-400">Pro Plan</div>
           </div>
           <div className="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 border-2 border-indigo-500/20 shadow-lg shadow-indigo-500/20"></div>
        </div>

      </div>
    </header>
  );
}
