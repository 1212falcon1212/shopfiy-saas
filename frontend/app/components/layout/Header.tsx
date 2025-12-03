"use client";

import { Bell, Search, Settings, Globe, Coins, Store, ChevronDown } from "lucide-react";
import { useSettings } from "../../context/SettingsContext";
import { useState, useRef, useEffect } from "react";

export function Header() {
  const { language, setLanguage, currency, setCurrency, selectedStore, stores, setSelectedStore, t } = useSettings();
  const [isStoreDropdownOpen, setIsStoreDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Dropdown dışına tıklanırsa kapat
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsStoreDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  return (
    <header className="h-20 flex items-center justify-between px-8 border-b border-slate-800/60 bg-[#0B1120]/80 backdrop-blur-md sticky top-0 z-30">
      
      {/* Sol: Mağaza Seçimi ve Selamlama */}
      <div className="flex items-center gap-6">
        
        {/* Mağaza Seçici */}
        <div className="relative" ref={dropdownRef}>
            <button 
                onClick={() => setIsStoreDropdownOpen(!isStoreDropdownOpen)}
                className="flex items-center gap-3 bg-slate-900/50 hover:bg-slate-800 border border-slate-800 rounded-xl px-4 py-2 transition-all group min-w-[200px]"
            >
                <div className="h-8 w-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:text-indigo-300 transition-colors">
                    <Store size={18} />
                </div>
                <div className="flex-1 text-left">
                    <p className="text-xs text-slate-500 font-medium">{selectedStore ? t('stores') : t('select_store')}</p>
                    <p className="text-sm font-semibold text-slate-200 truncate max-w-[120px]">
                        {selectedStore ? selectedStore.domain : t('no_store')}
                    </p>
                </div>
                <ChevronDown size={16} className={`text-slate-500 transition-transform ${isStoreDropdownOpen ? 'rotate-180' : ''}`} />
            </button>

            {isStoreDropdownOpen && (
                <div className="absolute top-full left-0 mt-2 w-64 bg-[#0B1120] border border-slate-800 rounded-xl shadow-xl overflow-hidden z-50">
                    <div className="p-2 max-h-64 overflow-y-auto">
                        {stores.length > 0 ? (
                            stores.map(store => (
                                <button
                                    key={store.id}
                                    onClick={() => {
                                        setSelectedStore(store);
                                        setIsStoreDropdownOpen(false);
                                    }}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors ${
                                        selectedStore?.id === store.id 
                                        ? 'bg-indigo-500/10 text-indigo-400' 
                                        : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
                                    }`}
                                >
                                    <div className={`h-2 w-2 rounded-full ${store.is_active ? 'bg-emerald-500' : 'bg-slate-600'}`} />
                                    <span className="truncate text-sm font-medium">{store.domain}</span>
                                    {selectedStore?.id === store.id && (
                                        <div className="ml-auto h-1.5 w-1.5 rounded-full bg-indigo-400" />
                                    )}
                                </button>
                            ))
                        ) : (
                            <div className="text-center py-4 text-sm text-slate-500">
                                {t('no_store')}
                            </div>
                        )}
                    </div>
                    <div className="p-2 border-t border-slate-800 bg-slate-900/30">
                        <a href="/stores/new" className="flex items-center justify-center gap-2 w-full py-2 text-xs font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-colors">
                            + {t('stores')} Ekle
                        </a>
                    </div>
                </div>
            )}
        </div>

      </div>

      {/* Sağ: Aksiyonlar */}
      <div className="flex items-center gap-4">
        
        {/* Dil Seçimi */}
        <div className="flex items-center gap-2">
            <button 
                onClick={() => setLanguage(language === 'tr' ? 'en' : 'tr')}
                className="flex items-center gap-1 p-2 text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-lg transition-all text-sm font-medium"
            >
                <Globe size={16} />
                <span className="uppercase">{language}</span>
            </button>

            <button 
                onClick={() => {
                    if (currency === 'TRY') setCurrency('USD');
                    else if (currency === 'USD') setCurrency('EUR');
                    else setCurrency('TRY');
                }}
                className="flex items-center gap-1 p-2 text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-lg transition-all text-sm font-medium"
            >
                <Coins size={16} />
                <span className="uppercase">{currency}</span>
            </button>
        </div>

        <div className="h-6 w-px bg-slate-800 mx-2" />

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
