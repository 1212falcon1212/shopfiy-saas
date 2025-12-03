"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { use } from "react"; // Next.js 15 params handling
import api from "../../../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../../../components/ui/Card";
import { Save, ArrowLeft, Settings, Key, Server, Store, RefreshCw, Info } from "lucide-react";
import Link from "next/link";

interface StoreSettings {
    id: number;
    domain: string;
    shopify_client_id: string;
    shopify_client_secret: string;
    access_token: string;
    shop_owner: string;
    email: string;
    kolaysoft_username: string;
    kolaysoft_password: string;
    kolaysoft_vkn_tckn: string;
    kolaysoft_supplier_name: string;
    is_active: boolean;
}

export default function StoreSettingsPage({ params }: { params: Promise<{ id: string }> }) {
    const router = useRouter();
    const resolvedParams = use(params);
    const storeId = resolvedParams.id;
    
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState<'general' | 'kolaysoft'>('general');
    const [store, setStore] = useState<StoreSettings | null>(null);

    const [redirectUrl, setRedirectUrl] = useState<string>('');
    const [reconnecting, setReconnecting] = useState(false);

    // Form states
    const [generalForm, setGeneralForm] = useState({
        domain: '',
        shopify_client_id: '',
        shopify_client_secret: '',
        shop_owner: '',
        email: ''
    });
    
    const [kolaysoftForm, setKolaysoftForm] = useState({
        kolaysoft_username: '',
        kolaysoft_password: '',
        kolaysoft_vkn_tckn: '',
        kolaysoft_supplier_name: ''
    });

    useEffect(() => {
        if (storeId) {
            fetchStoreSettings();
            fetchRedirectUrl();
        }
    }, [storeId]);

    const fetchRedirectUrl = async () => {
        try {
            const response = await api.get('/shopify/redirect-url');
            setRedirectUrl(response.data.redirect_uri);
        } catch (error) {
            console.error('Redirect URL alınamadı:', error);
        }
    };

    const fetchStoreSettings = async () => {
        try {
            const response = await api.get(`/stores/${storeId}`);
            const data = response.data;
            setStore(data);
            
            setGeneralForm({
                domain: data.domain || '',
                shopify_client_id: data.shopify_client_id || '',
                shopify_client_secret: data.shopify_client_secret || '',
                shop_owner: data.shop_owner || '',
                email: data.email || ''
            });

            setKolaysoftForm({
                kolaysoft_username: data.kolaysoft_username || '',
                kolaysoft_password: data.kolaysoft_password || '',
                kolaysoft_vkn_tckn: data.kolaysoft_vkn_tckn || '',
                kolaysoft_supplier_name: data.kolaysoft_supplier_name || ''
            });

        } catch (error) {
            console.error("Mağaza bilgileri alınamadı:", error);
            alert("Mağaza bilgileri yüklenirken hata oluştu.");
            router.push('/stores');
        } finally {
            setLoading(false);
        }
    };

    const handleSaveGeneral = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await api.put(`/stores/${storeId}`, generalForm);
            alert("Genel ayarlar güncellendi.");
            fetchStoreSettings(); // Yeniden yükle
        } catch (error: any) {
            console.error("Güncelleme hatası:", error);
            alert("Hata: " + (error.response?.data?.message || "Güncellenemedi."));
        } finally {
            setSaving(false);
        }
    };

    const handleReconnectOAuth = async () => {
        // Domain'den shop adını çıkar (tam domain kullan)
        let shop = generalForm.domain.trim();
        
        // https:// veya http:// kaldır
        shop = shop.replace(/^https?:\/\//, '');
        
        // Sonundaki / kaldır
        shop = shop.replace(/\/$/, '');
        
        // .myshopify.com yoksa ekle
        if (!shop.endsWith('.myshopify.com')) {
            // Eğer sadece shop adı varsa (örn: test-store), .myshopify.com ekle
            if (!shop.includes('.')) {
                shop = shop + '.myshopify.com';
            } else {
                alert('Geçersiz shop formatı. Örnek: test-store.myshopify.com');
                return;
            }
        }
        
        const clientId = generalForm.shopify_client_id;
        const clientSecret = generalForm.shopify_client_secret;

        if (!shop || !clientId || !clientSecret) {
            alert('Lütfen mağaza adı, Client ID ve Client Secret bilgilerini girin.');
            return;
        }

        // Shop formatını kontrol et
        if (!shop.match(/^[a-zA-Z0-9-]+\.myshopify\.com$/)) {
            alert('Geçersiz shop formatı. Örnek: test-store.myshopify.com');
            return;
        }

        setReconnecting(true);
        try {
            const response = await api.post('/shopify/initiate', {
                shop, // Tam domain: test-store-1100000000000000000000000000000002557.myshopify.com
                client_id: clientId,
                client_secret: clientSecret
            });
            
            if (response.data.auth_url) {
                window.location.href = response.data.auth_url;
            }
        } catch (error: any) {
            console.error('OAuth başlatma hatası:', error);
            alert('Hata: ' + (error.response?.data?.message || 'Shopify bağlantısı başlatılamadı.'));
            setReconnecting(false);
        }
    };

    const handleSaveKolaysoft = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await api.put(`/stores/${storeId}/kolaysoft-settings`, kolaysoftForm);
            alert("KolaySoft ayarları güncellendi.");
        } catch (error: any) {
            console.error("Güncelleme hatası:", error);
            alert("Hata: " + (error.response?.data?.message || "Güncellenemedi."));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="h-8 w-8 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin" />
            </div>
        );
    }

    return (
        <div className="max-w-4xl mx-auto animate-in fade-in zoom-in duration-500">
            <div className="mb-6">
                <Link href="/stores" className="text-slate-400 hover:text-white flex items-center gap-2 mb-4 transition-colors">
                    <ArrowLeft size={20} /> Mağazalara Dön
                </Link>
                <h1 className="text-3xl font-bold text-white tracking-tight">{store?.domain} - Ayarlar</h1>
            </div>

            {/* Tabs */}
            <div className="flex space-x-4 border-b border-slate-800 mb-8">
                <button
                    onClick={() => setActiveTab('general')}
                    className={`pb-3 px-4 font-medium text-sm transition-all relative ${
                        activeTab === 'general' 
                        ? 'text-indigo-400 border-b-2 border-indigo-500' 
                        : 'text-slate-400 hover:text-white'
                    }`}
                >
                    <div className="flex items-center gap-2">
                        <Settings size={16} /> Genel Ayarlar
                    </div>
                </button>
                <button
                    onClick={() => setActiveTab('kolaysoft')}
                    className={`pb-3 px-4 font-medium text-sm transition-all relative ${
                        activeTab === 'kolaysoft' 
                        ? 'text-indigo-400 border-b-2 border-indigo-500' 
                        : 'text-slate-400 hover:text-white'
                    }`}
                >
                    <div className="flex items-center gap-2">
                        <Server size={16} /> KolaySoft Entegrasyonu
                    </div>
                </button>
            </div>

            {activeTab === 'general' && (
                <Card className="bg-[#0B1120] border-slate-700/50">
                    <CardHeader>
                        <CardTitle className="text-white flex items-center gap-2">
                            <Settings size={20} className="text-indigo-400" /> Genel Mağaza Ayarları
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* OAuth Bağlantı Durumu */}
                        {store && (
                            <div className={`mb-6 p-4 rounded-lg border ${
                                store.is_active 
                                    ? 'bg-emerald-500/10 border-emerald-500/20' 
                                    : 'bg-amber-500/10 border-amber-500/20'
                            }`}>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className={`h-3 w-3 rounded-full ${store.is_active ? 'bg-emerald-500' : 'bg-amber-500'}`} />
                                        <div>
                                            <p className="text-sm font-medium text-white">
                                                {store.is_active ? 'Shopify Bağlantısı Aktif' : 'Shopify Bağlantısı Bekleniyor'}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                {store.is_active 
                                                    ? 'Mağaza başarıyla bağlanmış. OAuth ile yeniden bağlanmak için aşağıdaki butonu kullanın.'
                                                    : 'OAuth ile bağlanmak için aşağıdaki bilgileri doldurup "Yeniden Bağlan" butonuna tıklayın.'
                                                }
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <form onSubmit={handleSaveGeneral} className="space-y-6">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-300">Shopify Domain</label>
                                    <input 
                                        type="text" 
                                        value={generalForm.domain}
                                        onChange={(e) => setGeneralForm({...generalForm, domain: e.target.value})}
                                        className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                        placeholder="ornek-magaza.myshopify.com"
                                    />
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-300">Client ID</label>
                                        <input 
                                            type="text" 
                                            value={generalForm.shopify_client_id}
                                            onChange={(e) => setGeneralForm({...generalForm, shopify_client_id: e.target.value})}
                                            className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                            placeholder="Custom App Client ID"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-300">Client Secret</label>
                                        <input 
                                            type="password" 
                                            value={generalForm.shopify_client_secret}
                                            onChange={(e) => setGeneralForm({...generalForm, shopify_client_secret: e.target.value})}
                                            className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                            placeholder="Custom App Client Secret"
                                        />
                                    </div>
                                </div>

                                {redirectUrl && (
                                    <div className="p-3 bg-slate-900/50 border border-slate-700 rounded-lg">
                                        <p className="text-xs text-slate-400 mb-1">Custom App Redirect URL:</p>
                                        <p className="text-xs font-mono text-emerald-400 break-all">{redirectUrl}</p>
                                    </div>
                                )}

                                <div className="pt-2">
                                    <button
                                        type="button"
                                        onClick={handleReconnectOAuth}
                                        disabled={reconnecting || !generalForm.shopify_client_id || !generalForm.shopify_client_secret}
                                        className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-700 disabled:cursor-not-allowed text-white py-2.5 rounded-lg font-medium transition-all flex items-center justify-center gap-2"
                                    >
                                        {reconnecting ? (
                                            <>
                                                <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                                Yönlendiriliyor...
                                            </>
                                        ) : (
                                            <>
                                                <RefreshCw size={18} />
                                                OAuth ile Yeniden Bağlan
                                            </>
                                        )}
                                    </button>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-800">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-300">Mağaza Sahibi</label>
                                        <input 
                                            type="text" 
                                            value={generalForm.shop_owner}
                                            onChange={(e) => setGeneralForm({...generalForm, shop_owner: e.target.value})}
                                            className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-slate-300">E-posta</label>
                                        <input 
                                            type="email" 
                                            value={generalForm.email}
                                            onChange={(e) => setGeneralForm({...generalForm, email: e.target.value})}
                                            className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div className="pt-4 border-t border-slate-800 flex justify-end">
                                <button 
                                    type="submit" 
                                    disabled={saving}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2.5 rounded-lg font-medium transition-all shadow-lg shadow-indigo-500/25 flex items-center gap-2"
                                >
                                    {saving ? (
                                        <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                    ) : (
                                        <Save size={18} />
                                    )}
                                    Kaydet
                                </button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {activeTab === 'kolaysoft' && (
                <Card className="bg-[#0B1120] border-slate-700/50">
                    <CardHeader>
                        <CardTitle className="text-white flex items-center gap-2">
                            <Server size={20} className="text-indigo-400" /> KolaySoft API Ayarları
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSaveKolaysoft} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-300">Kullanıcı Adı</label>
                                    <input 
                                        type="text" 
                                        value={kolaysoftForm.kolaysoft_username}
                                        onChange={(e) => setKolaysoftForm({...kolaysoftForm, kolaysoft_username: e.target.value})}
                                        className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-300">Şifre</label>
                                    <input 
                                        type="password" 
                                        value={kolaysoftForm.kolaysoft_password}
                                        onChange={(e) => setKolaysoftForm({...kolaysoftForm, kolaysoft_password: e.target.value})}
                                        className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-300">VKN / TCKN</label>
                                    <input 
                                        type="text" 
                                        value={kolaysoftForm.kolaysoft_vkn_tckn}
                                        onChange={(e) => setKolaysoftForm({...kolaysoftForm, kolaysoft_vkn_tckn: e.target.value})}
                                        className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-300">Gönderici Firma Adı</label>
                                    <input 
                                        type="text" 
                                        value={kolaysoftForm.kolaysoft_supplier_name}
                                        onChange={(e) => setKolaysoftForm({...kolaysoftForm, kolaysoft_supplier_name: e.target.value})}
                                        className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/20 transition-all"
                                    />
                                </div>
                            </div>
                            <div className="pt-4 border-t border-slate-800 flex justify-end">
                                <button 
                                    type="submit" 
                                    disabled={saving}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2.5 rounded-lg font-medium transition-all shadow-lg shadow-indigo-500/25 flex items-center gap-2"
                                >
                                    {saving ? (
                                        <div className="h-4 w-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                    ) : (
                                        <Save size={18} />
                                    )}
                                    Kaydet
                                </button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
