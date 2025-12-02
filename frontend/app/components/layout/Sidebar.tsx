"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { 
  LayoutDashboard, 
  ShoppingCart, 
  Package, 
  UploadCloud, 
  Palette, 
  FileText, 
  Truck, 
  CreditCard,
  LogOut,
  Store,
  ChevronRight
} from "lucide-react";
import { cn } from "../ui/Card";

const navigation = [
  { name: "Genel Bakış", href: "/", icon: LayoutDashboard },
  { name: "Siparişler", href: "/orders", icon: ShoppingCart },
  { name: "Ürünler", href: "/products", icon: Package },
  { name: "XML Entegrasyon", href: "/xml-integration", icon: UploadCloud },
  { name: "Tema Yönetimi", href: "/themes", icon: Palette },
  { name: "Faturalar", href: "/invoices", icon: FileText },
  { name: "Kargo", href: "/shipping", icon: Truck },
];

const secondaryNavigation = [
  { name: "Plan & Ödeme", href: "/pricing", icon: CreditCard },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <div className="flex h-screen w-72 flex-col bg-[#0B1120] border-r border-slate-800/60 relative z-20">
      
      {/* Logo Alanı */}
      <div className="flex h-20 items-center px-8 border-b border-slate-800/60">
        <div className="flex items-center gap-3">
          <div className="h-8 w-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25">
            <Store size={18} strokeWidth={2.5} />
          </div>
          <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-200 to-slate-400">
            Panel<span className="text-indigo-500">.io</span>
          </span>
        </div>
      </div>

      {/* Ana Menü */}
      <div className="flex-1 overflow-y-auto py-8 px-4 space-y-8 scrollbar-hide">
        
        <div>
          <h3 className="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
            Yönetim
          </h3>
          <div className="space-y-1">
            {navigation.map((item) => {
              const isActive = pathname === item.href || pathname.startsWith(item.href + '/');
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={cn(
                    "group flex items-center justify-between px-4 py-2.5 text-sm font-medium rounded-xl transition-all duration-300",
                    isActive
                      ? "bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shadow-inner shadow-indigo-500/5"
                      : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-200 border border-transparent"
                  )}
                >
                  <div className="flex items-center">
                    <item.icon
                      className={cn(
                        "mr-3 h-5 w-5 flex-shrink-0 transition-colors duration-300",
                        isActive ? "text-indigo-400" : "text-slate-500 group-hover:text-slate-300"
                      )}
                    />
                    {item.name}
                  </div>
                  {isActive && (
                    <div className="h-1.5 w-1.5 rounded-full bg-indigo-400 shadow-[0_0_8px_rgba(129,140,248,0.8)]" />
                  )}
                </Link>
              );
            })}
          </div>
        </div>

        <div>
          <h3 className="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
            Hesap
          </h3>
          <div className="space-y-1">
            {secondaryNavigation.map((item) => {
              const isActive = pathname === item.href;
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={cn(
                    "group flex items-center px-4 py-2.5 text-sm font-medium rounded-xl transition-all duration-300",
                    isActive
                      ? "bg-indigo-500/10 text-indigo-400"
                      : "text-slate-400 hover:bg-slate-800/50 hover:text-slate-200"
                  )}
                >
                  <item.icon
                    className={cn(
                      "mr-3 h-5 w-5 flex-shrink-0 transition-colors",
                      isActive ? "text-indigo-400" : "text-slate-500 group-hover:text-slate-300"
                    )}
                  />
                  {item.name}
                </Link>
              );
            })}
          </div>
        </div>

      </div>

      {/* Alt Kısım - Mağaza Bilgisi & Çıkış */}
      <div className="p-4 border-t border-slate-800/60 bg-[#0F1623]">
        <div className="bg-slate-900/50 rounded-xl p-3 border border-slate-800/50 mb-3">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 border border-emerald-500/20">
              S
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-slate-200 truncate">Shopify Store</p>
              <p className="text-xs text-slate-500 truncate">store.myshopify.com</p>
            </div>
          </div>
        </div>
        
        <button className="flex w-full items-center justify-center px-4 py-2 text-sm font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-all duration-300">
          <LogOut className="mr-2 h-4 w-4" />
          Oturumu Kapat
        </button>
      </div>
    </div>
  );
}
