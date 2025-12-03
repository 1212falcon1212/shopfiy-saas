'use client';

import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import api from '../src/services/api'; // Yolu düzelttim: src app ile aynı seviyede olabilir veya app içinde olabilir.
// Dosya yapısına göre:
// frontend/app/context/SettingsContext.tsx
// frontend/app/src/services/api.ts -> O zaman ../src/services/api
// EĞER src app'in KARDEŞİ ise -> ../../src/services/api
// Listelemede: frontend/app/src/services/api.ts gördük. Yani app'in içinde src var.
// Bu durumda SettingsContext.tsx (app/context) -> ../src/services/api.ts DOĞRU.

type Language = 'tr' | 'en';
type Currency = 'TRY' | 'USD' | 'EUR';

interface Store {
    id: number;
    domain: string;
    shop_owner: string;
    email: string;
    is_active: boolean;
}

interface SettingsContextType {
  language: Language;
  currency: Currency;
  selectedStore: Store | null;
  stores: Store[];
  setLanguage: (lang: Language) => void;
  setCurrency: (curr: Currency) => void;
  setSelectedStore: (store: Store | null) => void;
  refreshStores: () => Promise<void>;
  t: (key: string) => string;
}

const SettingsContext = createContext<SettingsContextType | undefined>(undefined);

const translations: Record<Language, Record<string, string>> = {
  tr: {
    'dashboard': 'Kontrol Paneli',
    'products': 'Ürünler',
    'orders': 'Siparişler',
    'themes': 'Tema Mağazası',
    'ai_design': 'AI Tema Tasarımı',
    'xml_integration': 'XML Entegrasyonu',
    'settings': 'Ayarlar',
    'pricing': 'Fiyatlandırma',
    'select_plan': 'Plan Seç',
    'monthly': 'Aylık',
    'yearly': 'Yıllık',
    'current_plan': 'Mevcut Plan',
    'save_2_months': '2 ay tasarruf et',
    'per_month': '/ay',
    'per_year': '/yıl',
    'features': 'Özellikler',
    'most_popular': 'En Popüler',
    'logout': 'Çıkış Yap',
    'login': 'Giriş Yap',
    'register': 'Kayıt Ol',
    'payment': 'Ödeme',
    'stores': 'Mağazalarım',
    'select_store': 'Mağaza Seç',
    'no_store': 'Mağaza Yok',
  },
  en: {
    'dashboard': 'Dashboard',
    'products': 'Products',
    'orders': 'Orders',
    'themes': 'Theme Store',
    'ai_design': 'AI Theme Design',
    'xml_integration': 'XML Integration',
    'settings': 'Settings',
    'pricing': 'Pricing',
    'select_plan': 'Select Plan',
    'monthly': 'Monthly',
    'yearly': 'Yearly',
    'current_plan': 'Current Plan',
    'save_2_months': 'Save 2 months',
    'per_month': '/month',
    'per_year': '/year',
    'features': 'Features',
    'most_popular': 'Most Popular',
    'logout': 'Logout',
    'login': 'Login',
    'register': 'Register',
    'payment': 'Payment',
    'stores': 'My Stores',
    'select_store': 'Select Store',
    'no_store': 'No Store',
  }
};

export function SettingsProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>('tr');
  const [currency, setCurrency] = useState<Currency>('TRY');
  const [stores, setStores] = useState<Store[]>([]);
  const [selectedStore, setSelectedStore] = useState<Store | null>(null);

  useEffect(() => {
    const storedLang = localStorage.getItem('language') as Language;
    const storedCurr = localStorage.getItem('currency') as Currency;
    
    if (storedLang) setLanguage(storedLang);
    if (storedCurr) setCurrency(storedCurr);

    refreshStores();
  }, []);

  // Mağaza değiştiğinde kaydet (isteğe bağlı)
  useEffect(() => {
    if (selectedStore) {
        localStorage.setItem('selectedStoreId', selectedStore.id.toString());
    }
  }, [selectedStore]);

  const refreshStores = async () => {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    
    // Token yoksa istek atma
    if (!token) {
        setStores([]);
        setSelectedStore(null);
        return;
    }

    try {
        const response = await api.get('/stores');
        const storesData = response.data || [];
        setStores(storesData);

        // Önceden seçili mağazayı yükle veya ilkin seç
        const savedStoreId = localStorage.getItem('selectedStoreId');
        if (savedStoreId && storesData.length > 0) {
            const found = storesData.find((s: Store) => s.id.toString() === savedStoreId);
            if (found) setSelectedStore(found);
            else if (storesData.length > 0) setSelectedStore(storesData[0]);
        } else if (storesData.length > 0) {
            setSelectedStore(storesData[0]);
        } else {
            setSelectedStore(null);
        }
    } catch (error: any) {
        console.error("Mağazalar yüklenemedi:", error);
        // Hata durumunda boş array döndür (401 Unauthorized ise kullanıcı login olmamış demektir)
        if (error.response?.status === 401) {
            console.warn("Kullanıcı giriş yapmamış. Lütfen giriş yapın.");
            setStores([]);
            setSelectedStore(null);
        } else {
            // Diğer hatalar için de boş array
            setStores([]);
            setSelectedStore(null);
        }
    }
  };

  const handleSetLanguage = (lang: Language) => {
    setLanguage(lang);
    localStorage.setItem('language', lang);
  };

  const handleSetCurrency = (curr: Currency) => {
    setCurrency(curr);
    localStorage.setItem('currency', curr);
  };

  const t = (key: string) => {
    return translations[language][key] || key;
  };

  return (
    <SettingsContext.Provider value={{ 
        language, 
        currency, 
        selectedStore, 
        stores,
        setLanguage: handleSetLanguage, 
        setCurrency: handleSetCurrency, 
        setSelectedStore,
        refreshStores,
        t 
    }}>
      {children}
    </SettingsContext.Provider>
  );
}

export function useSettings() {
  const context = useContext(SettingsContext);
  if (context === undefined) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return context;
}
