"use client";

import { CheckCircle } from "lucide-react";
import Link from "next/link";
import { useSettings } from "../../../context/SettingsContext";

export default function PaymentSuccessPage() {
  const { t } = useSettings();

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] text-center space-y-6 animate-in fade-in zoom-in duration-500">
      <div className="h-24 w-24 bg-emerald-500/10 text-emerald-500 rounded-full flex items-center justify-center mb-4">
        <CheckCircle size={48} />
      </div>
      
      <h1 className="text-4xl font-bold text-white">Ödeme Başarılı!</h1>
      <p className="text-slate-400 max-w-md text-lg">
        Aboneliğiniz başarıyla başlatıldı. Artık tüm özelliklere erişebilirsiniz.
      </p>

      <div className="flex gap-4 mt-8">
        <Link 
            href="/dashboard"
            className="px-8 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition-all shadow-lg shadow-indigo-500/25"
        >
            Kontrol Paneline Git
        </Link>
      </div>
    </div>
  );
}

