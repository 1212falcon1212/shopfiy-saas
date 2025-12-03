"use client";

import { useEffect, useState, useRef } from "react";
import { X } from "lucide-react";
import api from "../../src/services/api";
import { useSettings } from "../../context/SettingsContext";

interface PaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  planId: number;
  planName: string;
  price: number;
  currency: string;
  interval: 'monthly' | 'yearly';
}

export function PaymentModal({ isOpen, onClose, planId, planName, price, currency, interval }: PaymentModalProps) {
  const { t } = useSettings();
  const [loading, setLoading] = useState(true);
  const [iframeToken, setIframeToken] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const iframeRef = useRef<HTMLIFrameElement>(null);

  useEffect(() => {
    if (isOpen && planId) {
      initializePayment();
    }
    
    // Cleanup
    return () => {
      setIframeToken(null);
      setError(null);
    };
  }, [isOpen, planId]);

  const initializePayment = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await api.post('/payment/init', {
        plan_id: planId,
        interval,
        currency
      });

      if (response.data.success) {
        setIframeToken(response.data.token);
      } else {
        setError(response.data.message || "Ödeme başlatılamadı.");
      }
    } catch (err: any) {
      console.error("Payment init error:", err);
      setError(err.response?.data?.message || "Ödeme sistemiyle bağlantı kurulamadı.");
    } finally {
      setLoading(false);
    }
  };

  // PayTR iFrame boyutlandırma mesajlarını dinle
  useEffect(() => {
    const handleMessage = (event: MessageEvent) => {
      if (event.origin === "https://www.paytr.com" && iframeRef.current) {
        // iFrame boyutlandırma mantığı gerekirse buraya eklenebilir
        // Genellikle PayTR iframe kendi kendini yönetir
      }
    };

    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, []);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="bg-[#0B1120] border border-slate-700 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col relative overflow-hidden">
        
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-800 bg-slate-900/50">
          <div>
            <h3 className="text-lg font-semibold text-white">{planName} - {t('payment')}</h3>
            <p className="text-sm text-slate-400">
               {price} {currency} / {interval === 'monthly' ? t('monthly') : t('yearly')}
            </p>
          </div>
          <button 
            onClick={onClose}
            className="p-2 hover:bg-slate-800 rounded-full text-slate-400 hover:text-white transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto bg-white p-0 relative min-h-[400px]">
            {loading && (
                <div className="absolute inset-0 flex items-center justify-center bg-[#0B1120] z-10">
                    <div className="flex flex-col items-center gap-3">
                        <div className="h-8 w-8 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin" />
                        <p className="text-slate-400 text-sm">Ödeme sayfası yükleniyor...</p>
                    </div>
                </div>
            )}

            {error ? (
                <div className="absolute inset-0 flex items-center justify-center bg-[#0B1120] z-10 p-8 text-center">
                    <div className="max-w-md">
                        <div className="h-16 w-16 bg-rose-500/10 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <X size={32} />
                        </div>
                        <h4 className="text-lg font-semibold text-white mb-2">Hata Oluştu</h4>
                        <p className="text-slate-400">{error}</p>
                        <button 
                            onClick={onClose}
                            className="mt-6 px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition-colors"
                        >
                            Kapat
                        </button>
                    </div>
                </div>
            ) : iframeToken ? (
                <iframe
                    ref={iframeRef}
                    src={`https://www.paytr.com/odeme/guvenli/${iframeToken}`}
                    className="w-full h-full min-h-[600px] border-0"
                    allowpaymentrequest="true"
                ></iframe>
            ) : null}
        </div>

      </div>
    </div>
  );
}

