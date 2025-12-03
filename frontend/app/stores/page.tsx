"use client";

import { useState, useEffect } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../components/ui/Card";
import { Plus, Store as StoreIcon, Trash2, Settings, ExternalLink } from "lucide-react";
import { useRouter } from "next/navigation";
import { useSettings } from "../context/SettingsContext";
import Link from "next/link";

interface Store {
    id: number;
    domain: string;
    shop_owner: string;
    email: string;
    is_active: boolean;
    created_at: string;
}

export default function StoresPage() {
    const { t } = useSettings();
    const router = useRouter();
    const [stores, setStores] = useState<Store[]>([]);
    const [loading, setLoading] = useState(true);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    useEffect(() => {
        fetchStores();
        
        // URL parametrelerinden hata/başarı mesajlarını kontrol et
        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');
        const success = params.get('success');
        
        if (error) {
            const errorMessages: { [key: string]: string } = {
                'invalid_request': 'Geçersiz istek. Lütfen tekrar deneyin.',
                'invalid_state': 'Geçersiz durum. Lütfen tekrar deneyin.',
                'store_not_found': 'Mağaza bulunamadı.',
                'invalid_hmac': 'HMAC doğrulaması başarısız. Client Secret\'ı kontrol edin.',
                'no_client_secret': 'Client Secret bulunamadı. Lütfen mağaza ayarlarından Client Secret\'ı girin.',
                'token_failed': 'Access token alınamadı. Lütfen tekrar deneyin.',
                'no_token': 'Access token bulunamadı.',
                'shop_info_failed': 'Mağaza bilgileri alınamadı.',
                'exception': 'Bir hata oluştu. Lütfen tekrar deneyin.',
            };
            setErrorMessage(errorMessages[error] || 'Bir hata oluştu.');
            
            // URL'den error parametresini kaldır
            window.history.replaceState({}, '', window.location.pathname);
        }
        
        if (success === 'store_added') {
            setSuccessMessage('Mağaza başarıyla eklendi!');
            
            // URL'den success parametresini kaldır
            window.history.replaceState({}, '', window.location.pathname);
        }
    }, []);

    const fetchStores = async () => {
        setLoading(true);
        try {
            const response = await api.get('/stores');
            setStores(response.data);
        } catch (error) {
            console.error("Mağazalar yüklenirken hata:", error);
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm("Bu mağazayı silmek istediğinize emin misiniz?")) return;

        try {
            await api.delete(`/stores/${id}`);
            setStores(stores.filter(s => s.id !== id));
        } catch (error) {
            console.error("Mağaza silinirken hata:", error);
            alert("Mağaza silinemedi.");
        }
    };

    return (
        <div className="space-y-8 animate-in fade-in zoom-in duration-500">
            {/* Hata Mesajı */}
            {errorMessage && (
                <div className="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-2 w-2 bg-red-500 rounded-full" />
                        <p className="text-sm text-red-400">{errorMessage}</p>
                    </div>
                    <button
                        onClick={() => setErrorMessage(null)}
                        className="text-red-400 hover:text-red-300"
                    >
                        ×
                    </button>
                </div>
            )}
            
            {/* Başarı Mesajı */}
            {successMessage && (
                <div className="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-2 w-2 bg-emerald-500 rounded-full" />
                        <p className="text-sm text-emerald-400">{successMessage}</p>
                    </div>
                    <button
                        onClick={() => setSuccessMessage(null)}
                        className="text-emerald-400 hover:text-emerald-300"
                    >
                        ×
                    </button>
                </div>
            )}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-white tracking-tight">Mağazalarım</h1>
                    <p className="text-slate-400 mt-2">Shopify mağazalarınızı buradan yönetin.</p>
                </div>
                <button 
                    onClick={() => router.push('/stores/new')}
                    className="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors shadow-lg shadow-indigo-500/20"
                >
                    <Plus size={20} />
                    <span>Mağaza Ekle</span>
                </button>
            </div>

            {loading ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {[1, 2, 3].map(i => (
                        <div key={i} className="h-48 bg-slate-800/50 rounded-xl animate-pulse"></div>
                    ))}
                </div>
            ) : stores.length === 0 ? (
                <div className="text-center py-20 bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl">
                    <div className="h-16 w-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-500">
                        <StoreIcon size={32} />
                    </div>
                    <h3 className="text-xl font-semibold text-white mb-2">Henüz Mağaza Yok</h3>
                    <p className="text-slate-400 mb-6">Shopify entegrasyonu için ilk mağazanızı ekleyin.</p>
                    <button 
                        onClick={() => router.push('/stores/new')}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2 rounded-lg inline-flex items-center gap-2 transition-colors"
                    >
                        <Plus size={20} />
                        Mağaza Ekle
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {stores.map(store => (
                        <Card key={store.id} className="bg-[#0B1120] border-slate-700/50 hover:border-indigo-500/30 transition-all group">
                            <CardHeader className="flex flex-row items-start justify-between pb-2">
                                <div className="flex items-center gap-3">
                                    <div className="h-10 w-10 bg-indigo-500/10 text-indigo-400 rounded-lg flex items-center justify-center">
                                        <StoreIcon size={20} />
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg text-white">{store.domain}</CardTitle>
                                        <span className={`text-xs px-2 py-0.5 rounded-full ${store.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700 text-slate-400'}`}>
                                            {store.is_active ? 'Aktif' : 'Pasif'}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <button 
                                        onClick={() => router.push(`/stores/${store.id}/settings`)}
                                        className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors"
                                        title="Ayarlar"
                                    >
                                        <Settings size={18} />
                                    </button>
                                    <button 
                                        onClick={() => handleDelete(store.id)}
                                        className="p-2 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                                        title="Sil"
                                    >
                                        <Trash2 size={18} />
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4 border-t border-slate-800/50 mt-4">
                                <div className="space-y-3 text-sm text-slate-400">
                                    <div className="flex justify-between">
                                        <span>Sahibi:</span>
                                        <span className="text-slate-200">{store.shop_owner || '-'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>E-posta:</span>
                                        <span className="text-slate-200">{store.email || '-'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Kayıt Tarihi:</span>
                                        <span className="text-slate-200">{new Date(store.created_at).toLocaleDateString('tr-TR')}</span>
                                    </div>
                                </div>
                                <div className="mt-6 flex gap-3">
                                    <Link 
                                        href={`https://${store.domain}`} 
                                        target="_blank"
                                        className="flex-1 bg-slate-800 hover:bg-slate-700 text-white py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                                    >
                                        <ExternalLink size={16} /> Ziyaret Et
                                    </Link>
                                    <Link 
                                        href={`/stores/${store.id}/settings`}
                                        className="flex-1 border border-slate-700 hover:bg-slate-800 text-slate-300 py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                                    >
                                        <Settings size={16} /> Yönet
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
