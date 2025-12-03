"use client";

import { useState, useEffect } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../components/ui/Card";
import { Check, CreditCard, Shield, Zap } from "lucide-react";
import { useRouter } from "next/navigation";
import { useSettings } from "../context/SettingsContext";
import { PaymentModal } from "../components/ui/PaymentModal";

interface Plan {
    id: number;
    name: Record<string, string>;
    description: Record<string, string>;
    price_try: string;
    price_usd: string;
    price_eur: string;
    interval: 'monthly' | 'yearly';
    features: Record<string, string[]>;
    is_active: boolean;
}

export default function PricingPage() {
  const router = useRouter();
  const { language, currency, t } = useSettings();
  const [loading, setLoading] = useState(false);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [interval, setInterval] = useState<'monthly' | 'yearly'>('monthly');
  
  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);

  useEffect(() => {
    fetchPlans();
  }, []);

  const fetchPlans = async () => {
    try {
        const response = await api.get('/plans');
        if (response.data.success) {
            setPlans(response.data.data);
        }
    } catch (error) {
        console.error("Planlar yüklenirken hata oluştu:", error);
    }
  };

  const handleSelectPlan = (plan: Plan) => {
    setSelectedPlan(plan);
    setIsModalOpen(true);
  };

  const getPrice = (plan: Plan) => {
    switch (currency) {
        case 'TRY': return parseFloat(plan.price_try);
        case 'USD': return parseFloat(plan.price_usd);
        case 'EUR': return parseFloat(plan.price_eur);
        default: return parseFloat(plan.price_usd);
    }
  };

  const getSymbol = () => {
    switch (currency) {
        case 'TRY': return '₺';
        case 'USD': return '$';
        case 'EUR': return '€';
        default: return '$';
    }
  };

  // Filtrele: Seçilen aralığa göre planları göster
  const displayedPlans = plans.filter(p => p.interval === interval);

  return (
    <div className="max-w-6xl mx-auto py-12 px-4 animate-in fade-in zoom-in duration-500">
      
      <div className="text-center mb-12 space-y-4">
        <h1 className="text-4xl font-bold text-white tracking-tight">
          {t('select_plan')}
        </h1>
        <p className="text-slate-400 max-w-2xl mx-auto text-lg">
          Shopify mağazanızı bir üst seviyeye taşıyın.
        </p>

        {/* Aylık / Yıllık Seçimi */}
        <div className="flex items-center justify-center gap-4 mt-8">
            <button 
                onClick={() => setInterval('monthly')}
                className={`px-6 py-2 rounded-full text-sm font-medium transition-all ${interval === 'monthly' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-slate-800 text-slate-400 hover:text-white'}`}
            >
                {t('monthly')}
            </button>
            <button 
                onClick={() => setInterval('yearly')}
                className={`px-6 py-2 rounded-full text-sm font-medium transition-all flex items-center gap-2 ${interval === 'yearly' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'bg-slate-800 text-slate-400 hover:text-white'}`}
            >
                {t('yearly')}
                <span className="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full">{t('save_2_months')}</span>
            </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {displayedPlans.map((plan) => (
            <div key={plan.id} className="relative group">
                <div className="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl blur opacity-0 group-hover:opacity-30 transition-opacity duration-500"></div>
                <Card className="relative bg-[#0B1120] border-slate-700/50 hover:border-indigo-500/50 shadow-xl transition-all h-full flex flex-col">
                    <CardHeader className="text-center pb-2">
                        <CardTitle className="text-2xl text-white">
                            {JSON.parse(plan.name as unknown as string)[language]}
                        </CardTitle>
                        <p className="text-slate-500 text-sm mt-2 min-h-[40px]">
                            {JSON.parse(plan.description as unknown as string)[language]}
                        </p>
                        <div className="flex justify-center items-baseline gap-1 mt-6">
                            <span className="text-4xl font-extrabold text-white">{getSymbol()}{getPrice(plan)}</span>
                            <span className="text-slate-500">{interval === 'monthly' ? t('per_month') : t('per_year')}</span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-8 pt-6 flex-1 flex flex-col">
                        <ul className="space-y-4 flex-1">
                            {JSON.parse(plan.features as unknown as string)[language].map((item: string, i: number) => (
                                <li key={i} className="flex items-center gap-3 text-slate-300">
                                    <div className="h-5 w-5 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
                                        <Check size={12} strokeWidth={3} />
                                    </div>
                                    <span className="text-sm">{item}</span>
                                </li>
                            ))}
                        </ul>

                        <button
                            onClick={() => handleSelectPlan(plan)}
                            disabled={loading}
                            className="w-full bg-slate-800 hover:bg-indigo-600 text-white py-3 rounded-xl font-bold transition-all shadow-lg hover:shadow-indigo-500/25 mt-8 flex items-center justify-center gap-2"
                        >
                            {t('select_plan')}
                        </button>
                    </CardContent>
                </Card>
            </div>
        ))}
      </div>

      {/* PayTR Modal */}
      {selectedPlan && (
          <PaymentModal 
            isOpen={isModalOpen}
            onClose={() => setIsModalOpen(false)}
            planId={selectedPlan.id}
            planName={JSON.parse(selectedPlan.name as unknown as string)[language]}
            price={getPrice(selectedPlan)}
            currency={currency}
            interval={interval}
          />
      )}

    </div>
  );
}
