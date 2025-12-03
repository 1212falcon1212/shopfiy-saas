'use client';

import { useState, useEffect } from 'react';
import { Card } from '../../components/ui/Card';
import { Save, Loader2, ShieldCheck } from 'lucide-react';

export default function IntegrationsSettingsPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState({
    kolaysoft_username: '',
    kolaysoft_password: '',
    kolaysoft_vkn_tckn: '',
    kolaysoft_supplier_name: '',
  });

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const res = await fetch('http://localhost:8000/api/stores/default', {
        headers: {
          'Accept': 'application/json',
          // Token eklenecek (Auth context'inden veya cookie'den)
        }
      });
      const data = await res.json();
      
      if (data) {
        setFormData({
          kolaysoft_username: data.kolaysoft_username || '',
          kolaysoft_password: data.kolaysoft_password || '', // Şifre genellikle *** olarak gelir ama burada düzenleme için
          kolaysoft_vkn_tckn: data.kolaysoft_vkn_tckn || '',
          kolaysoft_supplier_name: data.kolaysoft_supplier_name || '',
        });
      }
    } catch (error) {
      console.error('Ayarlar yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);

    try {
      const res = await fetch('http://localhost:8000/api/stores/default', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(formData)
      });
      
      const data = await res.json();
      
      if (data.success) {
        alert('Ayarlar başarıyla kaydedildi! 🎉');
      } else {
        alert('Hata oluştu: ' + JSON.stringify(data));
      }
    } catch (error) {
      console.error('Kaydetme hatası:', error);
      alert('Bağlantı hatası.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-blue-500" size={32} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Entegrasyon Ayarları 🔌</h1>
          <p className="text-gray-500 text-sm">Fatura ve diğer servisler için bağlantı bilgilerinizi yönetin.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* KolaySoft Ayarları */}
        <Card className="p-6 border-t-4 border-t-orange-500">
          <div className="flex items-center gap-3 mb-6">
            <div className="p-2 bg-orange-100 text-orange-600 rounded-lg">
              <ShieldCheck size={24} />
            </div>
            <div>
              <h2 className="text-lg font-bold text-gray-800">KolaySoft E-Fatura</h2>
              <p className="text-xs text-gray-500">E-Fatura ve E-Arşiv entegrasyonu.</p>
            </div>
          </div>

          <form onSubmit={handleSave} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Portal Kullanıcı Adı
              </label>
              <input
                type="text"
                value={formData.kolaysoft_username}
                onChange={(e) => setFormData({...formData, kolaysoft_username: e.target.value})}
                className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none"
                placeholder="admin_123456"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Portal Şifresi
              </label>
              <input
                type="password"
                value={formData.kolaysoft_password}
                onChange={(e) => setFormData({...formData, kolaysoft_password: e.target.value})}
                className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none"
                placeholder="••••••••"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  VKN / TCKN
                </label>
                <input
                  type="text"
                  value={formData.kolaysoft_vkn_tckn}
                  onChange={(e) => setFormData({...formData, kolaysoft_vkn_tckn: e.target.value})}
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none"
                  placeholder="1234567890"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Firma Adı (Faturada)
                </label>
                <input
                  type="text"
                  value={formData.kolaysoft_supplier_name}
                  onChange={(e) => setFormData({...formData, kolaysoft_supplier_name: e.target.value})}
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-orange-500 outline-none"
                  placeholder="Örn: ABC Ltd. Şti."
                />
              </div>
            </div>

            <div className="pt-4">
              <button
                type="submit"
                disabled={saving}
                className="w-full py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
              >
                {saving ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Kaydediliyor...
                  </>
                ) : (
                  <>
                    <Save size={18} />
                    Ayarları Kaydet
                  </>
                )}
              </button>
            </div>
          </form>
        </Card>

        {/* Diğer Entegrasyonlar (Placeholder) */}
        <Card className="p-6 opacity-50 pointer-events-none">
          <div className="flex items-center gap-3 mb-4">
            <div className="p-2 bg-blue-100 text-blue-600 rounded-lg">
              <div className="font-bold">K</div>
            </div>
            <div>
              <h2 className="text-lg font-bold text-gray-800">Kargo Entegrasyonu</h2>
              <p className="text-xs text-gray-500">Yurtiçi, MNG, Aras kargo ayarları.</p>
            </div>
          </div>
          <p className="text-sm text-gray-500">Yakında eklenecek...</p>
        </Card>
      </div>
    </div>
  );
}

