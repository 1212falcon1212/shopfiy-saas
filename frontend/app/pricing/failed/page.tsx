"use client";

import { XCircle } from "lucide-react";
import Link from "next/link";
import { useSettings } from "../../../context/SettingsContext";

export default function PaymentFailedPage() {
  const { t } = useSettings();

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] text-center space-y-6 animate-in fade-in zoom-in duration-500">
      <div className="h-24 w-24 bg-rose-500/10 text-rose-500 rounded-full flex items-center justify-center mb-4">
        <XCircle size={48} />
      </div>
      
      <h1 className="text-4xl font-bold text-white">Ödeme Başarısız</h1>
      <p className="text-slate-400 max-w-md text-lg">
        İşleminiz sırasında bir hata oluştu. Lütfen tekrar deneyin veya bankanızla iletişime geçin.
      </p>

      <div className="flex gap-4 mt-8">
        <Link 
            href="/pricing"
            className="px-8 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold transition-all"
        >
            Tekrar Dene
        </Link>
        <Link 
            href="/dashboard"
            className="px-8 py-3 border border-slate-700 hover:bg-slate-800 text-slate-300 rounded-xl font-bold transition-all"
        >
            Dashboard'a Dön
        </Link>
      </div>
    </div>
  );
}

