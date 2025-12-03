"use client";

import Link from "next/link";
import { ArrowRight, CheckCircle2, Zap, Globe, Shield, Layout, CreditCard } from "lucide-react";

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-[#0B1120] text-white selection:bg-indigo-500/30 selection:text-indigo-200">
      
      {/* Header */}
      <header className="fixed top-0 w-full z-50 border-b border-white/5 bg-[#0B1120]/80 backdrop-blur-xl">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold">
              P
            </div>
            <span className="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-200 to-slate-400">
              Panel<span className="text-indigo-500">.io</span>
            </span>
          </div>

          <nav className="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
            <Link href="#features" className="hover:text-white transition-colors">Özellikler</Link>
            <Link href="#pricing" className="hover:text-white transition-colors">Fiyatlar</Link>
            <Link href="#about" className="hover:text-white transition-colors">Hakkımızda</Link>
          </nav>

          <div className="flex items-center gap-4">
            <Link href="/login" className="text-sm font-medium text-slate-300 hover:text-white transition-colors">
              Giriş Yap
            </Link>
            <Link 
              href="/register" 
              className="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-all shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 hover:-translate-y-0.5"
            >
              Ücretsiz Başla
            </Link>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section className="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-indigo-500/20 rounded-full blur-[120px] -z-10 opacity-50 pointer-events-none" />
        
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-medium mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
            </span>
            Yeni Nesil E-ticaret Yönetimi
          </div>
          
          <h1 className="text-5xl md:text-7xl font-bold tracking-tight mb-8 animate-in fade-in slide-in-from-bottom-8 duration-1000">
            Shopify Mağazanızı <br />
            <span className="bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">
              Yapay Zeka ile Yönetin
            </span>
          </h1>
          
          <p className="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto mb-12 animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-200">
            Panel.io, Shopify mağazalarınızı tek bir yerden yönetmenizi, yapay zeka ile tema tasarlamanızı ve XML entegrasyonlarını otomatikleştirmenizi sağlar.
          </p>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-4 animate-in fade-in slide-in-from-bottom-16 duration-1000 delay-300">
            <Link 
              href="/register" 
              className="w-full sm:w-auto px-8 py-4 bg-white text-slate-900 rounded-full font-bold text-lg hover:bg-slate-200 transition-all flex items-center justify-center gap-2"
            >
              Hemen Başlayın <ArrowRight size={20} />
            </Link>
            <Link 
              href="#features" 
              className="w-full sm:w-auto px-8 py-4 bg-slate-800/50 text-white border border-slate-700 hover:bg-slate-800 rounded-full font-bold text-lg transition-all"
            >
              Daha Fazla Bilgi
            </Link>
          </div>

          {/* Dashboard Preview */}
          <div className="mt-20 relative mx-auto max-w-5xl animate-in fade-in zoom-in duration-1000 delay-500">
            <div className="rounded-xl border border-slate-800 bg-[#0F1623] p-2 shadow-2xl shadow-indigo-500/10">
              <div className="rounded-lg bg-slate-900 aspect-[16/9] overflow-hidden relative group">
                {/* Mockup Image Placeholder */}
                <div className="absolute inset-0 flex items-center justify-center text-slate-700">
                    <Layout size={64} />
                    <span className="ml-4 text-2xl font-semibold">Dashboard Önizlemesi</span>
                </div>
                <div className="absolute inset-0 bg-gradient-to-t from-[#0F1623] via-transparent to-transparent opacity-60" />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-24 bg-[#0F1623]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Tüm İhtiyaçlarınız Tek Platformda</h2>
            <p className="text-slate-400 max-w-2xl mx-auto">
              Shopify deneyiminizi geliştirmek için tasarlanmış güçlü özellikler.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <FeatureCard 
              icon={<Zap className="text-amber-400" />}
              title="XML Entegrasyonu"
              description="Tedarikçilerinizden gelen ürünleri saniyeler içinde mağazanıza aktarın. Stok ve fiyatları otomatik güncelleyin."
            />
            <FeatureCard 
              icon={<Layout className="text-indigo-400" />}
              title="AI Tema Tasarımı"
              description="Kod yazmadan, yapay zeka desteğiyle benzersiz ve dönüşüm odaklı temalar oluşturun."
            />
            <FeatureCard 
              icon={<Globe className="text-emerald-400" />}
              title="Çoklu Mağaza Yönetimi"
              description="Birden fazla Shopify mağazasını tek panelden yönetin. Geçiş yapmak hiç bu kadar kolay olmamıştı."
            />
            <FeatureCard 
              icon={<Shield className="text-rose-400" />}
              title="Güvenli Altyapı"
              description="Verileriniz endüstri standardı şifreleme ile korunur. Güvenle işinizi büyütün."
            />
            <FeatureCard 
              icon={<CreditCard className="text-blue-400" />}
              title="Kolay Ödeme"
              description="PayTR altyapısı ile güvenli ve hızlı abonelik yönetimi. İstediğiniz zaman iptal edin."
            />
            <FeatureCard 
              icon={<CheckCircle2 className="text-purple-400" />}
              title="7/24 Destek"
              description="Takıldığınız her noktada uzman ekibimiz yanınızda. İşiniz asla yarım kalmaz."
            />
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section id="pricing" className="py-24 relative overflow-hidden">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-indigo-500/10 rounded-full blur-[100px] -z-10 pointer-events-none" />
        
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Esnek Fiyatlandırma</h2>
            <p className="text-slate-400 max-w-2xl mx-auto">
              İşletmenizin büyüklüğüne uygun planı seçin.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {/* Starter */}
            <PricingCard 
              title="Başlangıç"
              price="199.90"
              features={[
                "1 Mağaza Bağlantısı",
                "Temel İstatistikler",
                "KolaySoft Entegrasyonu",
                "E-posta Desteği"
              ]}
            />
            
            {/* Pro */}
            <PricingCard 
              title="Profesyonel"
              price="399.90"
              isPopular
              features={[
                "5 Mağaza Bağlantısı",
                "Gelişmiş Raporlama",
                "AI Tema Sihirbazı",
                "Öncelikli Destek",
                "Tüm Entegrasyonlar"
              ]}
            />

            {/* Enterprise */}
            <PricingCard 
              title="Kurumsal"
              price="Özel"
              features={[
                "Sınırsız Mağaza",
                "Özel Geliştirmeler",
                "Dedicated Sunucu",
                "7/24 Telefon Desteği"
              ]}
              buttonText="İletişime Geç"
            />
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-slate-800 bg-[#0F1623] py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-8">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-500 flex items-center justify-center text-white font-bold">
              P
            </div>
            <span className="text-xl font-bold text-white">
              Panel<span className="text-indigo-500">.io</span>
            </span>
          </div>
          
          <div className="flex gap-8 text-slate-400 text-sm">
            <Link href="#" className="hover:text-white transition-colors">Gizlilik Politikası</Link>
            <Link href="#" className="hover:text-white transition-colors">Kullanım Koşulları</Link>
            <Link href="#" className="hover:text-white transition-colors">İletişim</Link>
          </div>

          <div className="text-slate-500 text-sm">
            &copy; 2025 Panel.io. Tüm hakları saklıdır.
          </div>
        </div>
      </footer>

    </div>
  );
}

function FeatureCard({ icon, title, description }: { icon: React.ReactNode, title: string, description: string }) {
  return (
    <div className="p-6 rounded-2xl bg-[#0B1120] border border-slate-800 hover:border-indigo-500/50 transition-all hover:-translate-y-1 group">
      <div className="h-12 w-12 rounded-lg bg-slate-900 flex items-center justify-center mb-4 group-hover:bg-slate-800 transition-colors">
        {icon}
      </div>
      <h3 className="text-xl font-bold mb-2 text-white">{title}</h3>
      <p className="text-slate-400 leading-relaxed">{description}</p>
    </div>
  );
}

function PricingCard({ title, price, features, isPopular, buttonText = "Planı Seç" }: { title: string, price: string, features: string[], isPopular?: boolean, buttonText?: string }) {
  return (
    <div className={`relative p-8 rounded-2xl border ${isPopular ? 'bg-[#0B1120] border-indigo-500 shadow-2xl shadow-indigo-500/10' : 'bg-[#0F1623] border-slate-800'} flex flex-col`}>
      {isPopular && (
        <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-semibold shadow-lg">
          En Popüler
        </div>
      )}
      
      <h3 className="text-2xl font-bold text-white mb-2">{title}</h3>
      <div className="flex items-baseline gap-1 mb-6">
        <span className="text-4xl font-extrabold text-white">{price !== "Özel" ? '₺' : ''}{price}</span>
        {price !== "Özel" && <span className="text-slate-500">/ay</span>}
      </div>

      <ul className="space-y-4 mb-8 flex-1">
        {features.map((feature, index) => (
          <li key={index} className="flex items-center gap-3 text-slate-300">
            <CheckCircle2 size={18} className="text-emerald-400 flex-shrink-0" />
            <span className="text-sm">{feature}</span>
          </li>
        ))}
      </ul>

      <Link 
        href="/register" 
        className={`w-full py-3 rounded-xl font-bold text-center transition-all ${isPopular ? 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-500/25' : 'bg-slate-800 hover:bg-slate-700 text-white'}`}
      >
        {buttonText}
      </Link>
    </div>
  );
}

