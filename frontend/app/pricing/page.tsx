"use client";

import { useState } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../components/ui/Card";
import { Check, CreditCard, Shield, Zap } from "lucide-react";
import { useRouter } from "next/navigation";

export default function PricingPage() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  const handleSelectPlan = async () => {
    setLoading(true);
    try {
      const response = await api.get("/billing");
      
      if (response.data.confirmation_url) {
        // Shopify Ödeme Onay Sayfasına Yönlendir
        // window.location.href, Next.js router.push yerine kullanılır çünkü dış link
        window.location.href = response.data.confirmation_url;
      } else {
        alert("Ödeme sayfası oluşturulamadı.");
        setLoading(false);
      }
    } catch (err: any) {
      console.error("Plan seçimi hatası:", err);
      alert("Bir hata oluştu: " + (err.response?.data?.error || "Bilinmeyen hata"));
      setLoading(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto py-12 px-4 animate-in fade-in zoom-in duration-500">
      
      <div className="text-center mb-16 space-y-4">
        <h1 className="text-4xl font-bold text-white tracking-tight">
          İşletmeniz İçin <span className="text-indigo-400">En İyi Planı</span> Seçin
        </h1>
        <p className="text-slate-400 max-w-2xl mx-auto text-lg">
          Shopify mağazanızı bir üst seviyeye taşıyın. Tüm özelliklere sınırsız erişim sağlayın.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
        
        {/* Sol: Özellikler */}
        <div className="md:col-span-1 space-y-6 pt-12">
            <div className="flex items-center gap-4 text-slate-300">
                <div className="h-10 w-10 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                    <Zap size={20} />
                </div>
                <div>
                    <h3 className="font-semibold text-white">Hızlı Entegrasyon</h3>
                    <p className="text-sm text-slate-500">XML ürünlerinizi dakikalar içinde aktarın.</p>
                </div>
            </div>
            <div className="flex items-center gap-4 text-slate-300">
                <div className="h-10 w-10 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <Shield size={20} />
                </div>
                <div>
                    <h3 className="font-semibold text-white">Güvenli Altyapı</h3>
                    <p className="text-sm text-slate-500">Verileriniz güvende ve her an erişilebilir.</p>
                </div>
            </div>
        </div>

        {/* Orta: Plan Kartı */}
        <div className="md:col-span-1 relative">
            <div className="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl blur opacity-30 animate-pulse"></div>
            <Card className="relative bg-[#0B1120] border-slate-700/50 shadow-2xl scale-105">
                <CardHeader className="text-center pb-2">
                   <div className="mx-auto w-fit px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-full text-xs font-semibold mb-4 uppercase tracking-wider">
                      En Popüler
                   </div>
                   <CardTitle className="text-2xl text-white">Pro Plan</CardTitle>
                   <div className="flex justify-center items-baseline gap-1 mt-4">
                      <span className="text-5xl font-extrabold text-white">$19.90</span>
                      <span className="text-slate-500">/ay</span>
                   </div>
                </CardHeader>
                <CardContent className="space-y-8 pt-6">
                    <ul className="space-y-4">
                        {[
                            "Sınırsız XML Entegrasyonu",
                            "Otomatik Stok & Fiyat Güncelleme",
                            "Gelişmiş Ürün Yönetimi",
                            "Toplu İşlemler",
                            "Öncelikli Destek"
                        ].map((item, i) => (
                            <li key={i} className="flex items-center gap-3 text-slate-300">
                                <div className="h-5 w-5 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
                                    <Check size={12} strokeWidth={3} />
                                </div>
                                <span className="text-sm">{item}</span>
                            </li>
                        ))}
                    </ul>

                    <button
                        onClick={handleSelectPlan}
                        disabled={loading}
                        className="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-4 rounded-xl font-bold text-lg transition-all shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:-translate-y-1 flex items-center justify-center gap-2"
                    >
                        {loading ? (
                            <div className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                        ) : (
                            <>
                                <CreditCard size={20} /> Planı Seç
                            </>
                        )}
                    </button>
                    <p className="text-xs text-center text-slate-500">
                        3 gün ücretsiz deneme. İstediğiniz zaman iptal edebilirsiniz.
                    </p>
                </CardContent>
            </Card>
        </div>

        {/* Sağ: Boşluk veya Diğer Bilgiler */}
        <div className="md:col-span-1"></div>

      </div>
    </div>
  );
}

