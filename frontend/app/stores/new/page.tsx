"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import api from "../../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../../components/ui/Card";
import { Store, ArrowLeft, Save, Info } from "lucide-react";
import Link from "next/link";

export default function NewStorePage() {
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    const [redirectUrl, setRedirectUrl] = useState<string>('');

    // Redirect URL'i backend'den al
    useEffect(() => {
        api.get('/shopify/redirect-url')
            .then(response => {
                setRedirectUrl(response.data.redirect_uri);
            })
            .catch(error => {
                console.error('Redirect URL alınamadı:', error);
                // Fallback
                setRedirectUrl('https://josue-untruckling-nikki.ngrok-free.dev/api/shopify/callback');
            });
    }, []);

    return (
        <div className="max-w-2xl mx-auto animate-in fade-in zoom-in duration-500">
            <div className="mb-6">
                <Link href="/stores" className="text-slate-400 hover:text-white flex items-center gap-2 mb-4 transition-colors">
                    <ArrowLeft size={20} /> Mağazalara Dön
                </Link>
                <h1 className="text-3xl font-bold text-white tracking-tight">Yeni Mağaza Ekle</h1>
                <p className="text-slate-400 mt-2">Shopify mağazanızı panele bağlayın.</p>
            </div>

            <Card className="bg-[#0B1120] border-slate-700/50">
                <CardHeader>
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 bg-indigo-500/10 text-indigo-400 rounded-lg flex items-center justify-center">
                            <Store size={20} />
                        </div>
                        <CardTitle className="text-white">Mağaza Bilgileri</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    {/* Shopify OAuth ile Bağlan (Custom App - Önerilen) */}
                    <div className="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg">
                        <h3 className="text-sm font-semibold text-emerald-400 mb-2">Shopify OAuth ile Bağlan (Önerilen)</h3>
                        <p className="text-xs text-slate-400 mb-3">
                            Custom App kullanarak Shopify mağazanızı bağlayın. Önce Shopify Admin'den Custom App oluşturmanız gerekiyor.
                        </p>
                        
                        {/* Scope Rehberi */}
                        <div className="mb-4 p-3 bg-slate-900/50 border border-slate-700 rounded-lg">
                            <h4 className="text-xs font-semibold text-slate-300 mb-2 flex items-center gap-2">
                                <Info size={14} />
                                Custom App Oluştururken Seçmeniz Gereken İzinler (Scopes):
                            </h4>
                            <div className="space-y-1.5 text-xs text-slate-400">
                                <div className="flex items-start gap-2">
                                    <span className="text-emerald-400 mt-0.5">✓</span>
                                    <div>
                                        <span className="font-medium text-slate-300">read_products, write_products</span>
                                        <span className="text-slate-500 ml-1">- Ürün yönetimi için</span>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="text-emerald-400 mt-0.5">✓</span>
                                    <div>
                                        <span className="font-medium text-slate-300">read_orders, write_orders</span>
                                        <span className="text-slate-500 ml-1">- Sipariş yönetimi ve fatura oluşturma için</span>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="text-emerald-400 mt-0.5">✓</span>
                                    <div>
                                        <span className="font-medium text-slate-300">read_custom_collections, write_custom_collections</span>
                                        <span className="text-slate-500 ml-1">- Koleksiyon yönetimi için</span>
                                    </div>
                                </div>
                                <div className="flex items-start gap-2">
                                    <span className="text-emerald-400 mt-0.5">✓</span>
                                    <div>
                                        <span className="font-medium text-slate-300">write_themes</span>
                                        <span className="text-slate-500 ml-1">- Tema yükleme için</span>
                                    </div>
                                </div>
                            </div>
                            <p className="text-xs text-slate-500 mt-2 pt-2 border-t border-slate-700">
                                <strong>Not:</strong> Custom App oluştururken "Admin API access scopes" bölümünde yukarıdaki tüm izinleri seçin.
                            </p>
                        </div>
                        
                        {/* Redirect URL Rehberi */}
                        {redirectUrl && (
                            <div className="mb-4 p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg">
                                <h4 className="text-xs font-semibold text-amber-400 mb-2 flex items-center gap-2">
                                    <Info size={14} />
                                    ÖNEMLİ: Custom App'te Redirect URL Ayarlama
                                </h4>
                                <div className="space-y-2 text-xs text-slate-400">
                                    <p>
                                        Custom App oluştururken <strong className="text-amber-400">"Allowed redirection URL(s)"</strong> veya <strong className="text-amber-400">"Redirect URL"</strong> alanına şu URL'yi girmeniz gerekiyor:
                                    </p>
                                    <div className="p-2 bg-slate-900/50 border border-slate-700 rounded font-mono text-xs text-emerald-400 break-all">
                                        {redirectUrl}
                                    </div>
                                    <p className="text-slate-500">
                                        <strong>Adımlar:</strong>
                                    </p>
                                    <ol className="list-decimal list-inside space-y-1 text-slate-500 ml-2">
                                        <li>Custom App oluştururken "Configuration" veya "API access" bölümüne gidin</li>
                                        <li>"Allowed redirection URL(s)" veya "Redirect URL" alanını bulun</li>
                                        <li>Yukarıdaki URL'yi bu alana girin</li>
                                        <li>Kaydedin ve Client ID/Secret'ı kopyalayın</li>
                                    </ol>
                                    <p className="text-amber-400 font-semibold mt-2">
                                        ⚠️ Bu URL'yi Custom App'e eklemeden OAuth çalışmaz!
                                    </p>
                                </div>
                            </div>
                        )}
                        <div className="space-y-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-slate-300">Shopify Mağaza Adı</label>
                                <input 
                                    type="text" 
                                    placeholder="ornek-magaza" 
                                    id="shop-input"
                                    className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition-all"
                                />
                                <p className="text-xs text-slate-500">
                                    Sadece mağaza adını girin (örn: ornek-magaza). .myshopify.com otomatik eklenir.
                                </p>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-slate-300">Client ID</label>
                                <input 
                                    type="text" 
                                    placeholder="Custom App Client ID" 
                                    id="client-id-input"
                                    className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition-all"
                                />
                                <p className="text-xs text-slate-500">
                                    Shopify Admin &gt; Ayarlar &gt; Uygulamalar ve satış kanalları &gt; Uygulamalar geliştirin &gt; Custom App oluşturun
                                </p>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-slate-300">Client Secret</label>
                                <input 
                                    type="password" 
                                    placeholder="Custom App Client Secret" 
                                    id="client-secret-input"
                                    className="w-full bg-slate-900/50 border border-slate-800 rounded-lg px-4 py-2.5 text-slate-200 focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/20 transition-all"
                                />
                            </div>
                            <button
                                type="button"
                                onClick={async () => {
                                    const shopInput = document.getElementById('shop-input') as HTMLInputElement;
                                    const clientIdInput = document.getElementById('client-id-input') as HTMLInputElement;
                                    const clientSecretInput = document.getElementById('client-secret-input') as HTMLInputElement;
                                    
                                    const shop = shopInput?.value.trim();
                                    const clientId = clientIdInput?.value.trim();
                                    const clientSecret = clientSecretInput?.value.trim();
                                    
                                    if (!shop || !clientId || !clientSecret) {
                                        alert('Lütfen tüm alanları doldurun.');
                                        return;
                                    }

                                    try {
                                        const response = await api.post('/shopify/initiate', { 
                                            shop,
                                            client_id: clientId,
                                            client_secret: clientSecret
                                        });
                                        if (response.data.auth_url) {
                                            window.location.href = response.data.auth_url;
                                        }
                                    } catch (error: any) {
                                        console.error('OAuth başlatma hatası:', error);
                                        alert('Hata: ' + (error.response?.data?.message || 'Shopify bağlantısı başlatılamadı.'));
                                    }
                                }}
                                className="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-lg font-medium transition-all flex items-center justify-center gap-2"
                            >
                                <Store size={18} />
                                Shopify ile Bağlan
                            </button>
                        </div>
                    </div>

                </CardContent>
            </Card>
        </div>
    );
}
