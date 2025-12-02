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
  Settings,
  LogOut
} from "lucide-react";

const navigation = [
  { name: "Genel Bakış", href: "/", icon: LayoutDashboard },
  { name: "Siparişler", href: "/orders", icon: ShoppingCart },
  { name: "Ürünler", href: "/products", icon: Package },
  { name: "XML Entegrasyon", href: "/xml-integration", icon: UploadCloud },
  { name: "Tema Ayarları", href: "/themes", icon: Palette },
  { name: "Fatura Ayarları", href: "/invoices", icon: FileText },
  { name: "Kargo Ayarları", href: "/shipping", icon: Truck },
  { name: "Fiyatlandırma", href: "/pricing", icon: CreditCard },
];

export default function Sidebar() {
  const pathname = usePathname();

  return (
    <div className="flex h-screen w-72 flex-col bg-slate-900 border-r border-slate-800 text-slate-300">
      
      {/* Logo Alanı */}
      <div className="flex h-20 items-center justify-center border-b border-slate-800 px-6">
        <h1 className="text-2xl font-bold bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">
          SaaS Panel
        </h1>
      </div>

      {/* Menü Linkleri */}
      <div className="flex-1 overflow-y-auto py-6 px-4 space-y-1">
        {navigation.map((item) => {
          const isActive = pathname === item.href;
          return (
            <Link
              key={item.name}
              href={item.href}
              className={`group flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-200 ${
                isActive
                  ? "bg-indigo-600 text-white shadow-lg shadow-indigo-500/30"
                  : "text-slate-400 hover:bg-slate-800 hover:text-white"
              }`}
            >
              <item.icon
                className={`mr-3 h-5 w-5 flex-shrink-0 transition-colors ${
                  isActive ? "text-white" : "text-slate-500 group-hover:text-white"
                }`}
              />
              {item.name}
            </Link>
          );
        })}
      </div>

      {/* Alt Kısım (Kullanıcı / Çıkış) */}
      <div className="border-t border-slate-800 p-4">
        <button className="group flex w-full items-center px-4 py-3 text-sm font-medium text-slate-400 rounded-xl hover:bg-red-500/10 hover:text-red-400 transition-colors">
          <LogOut className="mr-3 h-5 w-5 text-slate-500 group-hover:text-red-400" />
          Çıkış Yap
        </button>
      </div>
    </div>
  );
}