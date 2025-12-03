'use client';

import { useState, useEffect } from 'react';
import { Card } from '../components/ui/Card';
import { api } from '../src/services/api';
import { Layout, Download, Check, Loader2, Upload, X } from 'lucide-react';

interface Theme {
  id: number;
  name: string;
  description: string;
  preview_image?: string;
  is_active: boolean;
}

export default function ThemesPage() {
  const [themes, setThemes] = useState<Theme[]>([]);
  const [loading, setLoading] = useState(true);
  const [installingId, setInstallingId] = useState<number | null>(null);
  const [isAdmin, setIsAdmin] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [uploadForm, setUploadForm] = useState({
    name: '',
    description: '',
    zip_file: null as File | null
  });

  useEffect(() => {
    fetchThemes();
    checkAdmin();
  }, []);

  const checkAdmin = async () => {
    try {
      // Kullanıcı bilgilerini çek (role kontrolü için)
      const res = await fetch('http://localhost:8000/api/user', {
        headers: {
          'Accept': 'application/json',
        }
      });
      
      if (!res.ok) {
        console.warn('User endpoint hatası:', res.status);
        setIsAdmin(false);
        return;
      }
      
      const user = await res.json();
      setIsAdmin(user?.role === 'admin');
    } catch (error) {
      console.error('Admin kontrolü yapılamadı:', error);
      setIsAdmin(false);
    }
  };

  const fetchThemes = async () => {
    try {
      const res = await fetch('http://localhost:8000/api/themes');
      const data = await res.json();
      setThemes(data);
    } catch (error) {
      console.error('Temalar yüklenirken hata:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleInstall = async (id: number) => {
    setInstallingId(id);
    try {
      const res = await fetch(`http://localhost:8000/api/themes/${id}/install`, {
        method: 'POST'
      });
      const data = await res.json();
      
      if (data.success) {
        alert(data.message);
      } else {
        alert('Tema yüklenirken bir hata oluştu.');
      }
    } catch (error) {
      console.error('Install Error:', error);
      alert('Bağlantı hatası.');
    } finally {
      setInstallingId(null);
    }
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadForm.zip_file) {
      alert('Lütfen bir ZIP dosyası seçin.');
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('name', uploadForm.name);
      formData.append('description', uploadForm.description);
      formData.append('zip_file', uploadForm.zip_file);

      const res = await fetch('http://localhost:8000/api/themes/upload', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();
      
      if (data.success) {
        alert('Tema başarıyla yüklendi! 🎉');
        setShowUploadModal(false);
        setUploadForm({ name: '', description: '', zip_file: null });
        fetchThemes(); // Listeyi yenile
      } else {
        alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
      }
    } catch (error) {
      console.error('Upload Error:', error);
      alert('Bağlantı hatası.');
    } finally {
      setUploading(false);
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
          <h1 className="text-2xl font-bold text-gray-800">Tema Mağazası 🛍️</h1>
          <p className="text-gray-500 text-sm">Hazır temalarımızı tek tıkla mağazanıza kurun.</p>
        </div>
        {isAdmin && (
          <button
            onClick={() => setShowUploadModal(true)}
            className="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold flex items-center gap-2 transition-colors"
          >
            <Upload size={18} />
            Tema Yükle
          </button>
        )}
      </div>

      {/* Upload Modal (Admin Only) */}
      {showUploadModal && isAdmin && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <Card className="p-6 max-w-md w-full mx-4 relative">
            <button
              onClick={() => setShowUploadModal(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
            >
              <X size={20} />
            </button>
            
            <h2 className="text-xl font-bold mb-4">Yeni Tema Yükle</h2>
            
            <form onSubmit={handleUpload} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Tema Adı
                </label>
                <input
                  type="text"
                  value={uploadForm.name}
                  onChange={(e) => setUploadForm({...uploadForm, name: e.target.value})}
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Açıklama
                </label>
                <textarea
                  value={uploadForm.description}
                  onChange={(e) => setUploadForm({...uploadForm, description: e.target.value})}
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                  rows={3}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  ZIP Dosyası (Max 10MB)
                </label>
                <input
                  type="file"
                  accept=".zip"
                  onChange={(e) => setUploadForm({...uploadForm, zip_file: e.target.files?.[0] || null})}
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
                  required
                />
              </div>

              <div className="flex gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowUploadModal(false)}
                  className="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                  İptal
                </button>
                <button
                  type="submit"
                  disabled={uploading}
                  className="flex-1 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {uploading ? (
                    <>
                      <Loader2 size={18} className="animate-spin" />
                      Yükleniyor...
                    </>
                  ) : (
                    <>
                      <Upload size={18} />
                      Yükle
                    </>
                  )}
                </button>
              </div>
            </form>
          </Card>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {themes.map((theme) => (
          <Card key={theme.id} className="overflow-hidden flex flex-col group">
            {/* Preview Image */}
            <div className="h-48 bg-gray-100 relative overflow-hidden">
              {theme.preview_image ? (
                <img 
                  src={theme.preview_image} 
                  alt={theme.name} 
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
              ) : (
                <div className="flex items-center justify-center h-full text-gray-300">
                  <Layout size={48} />
                </div>
              )}
              
              {/* Overlay Badge */}
              <div className="absolute top-3 right-3 bg-white/90 backdrop-blur px-2 py-1 rounded text-xs font-bold shadow-sm text-gray-700">
                Ücretsiz
              </div>
            </div>

            <div className="p-5 flex flex-col flex-1">
              <h3 className="font-bold text-lg text-gray-900 mb-2">{theme.name}</h3>
              <p className="text-sm text-gray-500 mb-4 flex-1">{theme.description}</p>
              
              <button
                onClick={() => handleInstall(theme.id)}
                disabled={installingId === theme.id}
                className={`w-full py-3 rounded-lg font-medium flex items-center justify-center gap-2 transition-all
                  ${installingId === theme.id
                    ? 'bg-blue-100 text-blue-700'
                    : 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/30'}`}
              >
                {installingId === theme.id ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Kuruluyor...
                  </>
                ) : (
                  <>
                    <Download size={18} />
                    Mağazaya Kur
                  </>
                )}
              </button>
            </div>
          </Card>
        ))}
      </div>
      
      {themes.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          Henüz hiç tema bulunmuyor.
        </div>
      )}
    </div>
  );
}
