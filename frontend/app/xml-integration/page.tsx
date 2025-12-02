"use client";

import { useEffect, useState } from "react";
import api from "../src/services/api";
import { Card, CardHeader, CardTitle, CardContent } from "../components/ui/Card";
import { Badge } from "../components/ui/Badge";
import { UploadCloud, Plus, MoreHorizontal, RefreshCw, Trash2, Edit, ExternalLink, Play } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";

interface XmlIntegration {
  id: number;
  xml_url: string;
  is_active: boolean;
  last_sync_at: string | null;
  created_at: string;
}

export default function XmlIntegrationListPage() {
  const [integrations, setIntegrations] = useState<XmlIntegration[]>([]);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    fetchIntegrations();
  }, []);

  const fetchIntegrations = () => {
    setLoading(true);
    api.get("/xml/integrations") // Backend endpoint
      .then((res) => {
        setIntegrations(res.data);
        setLoading(false);
      })
      .catch((err) => {
        console.error("Entegrasyonlar alınamadı", err);
        setLoading(false);
      });
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Bu entegrasyonu silmek istediğinize emin misiniz?")) return;

    try {
      await api.delete(`/xml/integrations/${id}`);
      setIntegrations(prev => prev.filter(i => i.id !== id));
    } catch (err) {
      alert("Silme işlemi başarısız oldu.");
    }
  };

  const handleSyncNow = async (id: number) => {
     try {
       await api.post(`/xml/integrations/${id}/sync`);
       alert("Senkronizasyon başlatıldı! Arka planda çalışıyor.");
     } catch(err) {
       alert("Senkronizasyon başlatılamadı.");
     }
  };

  return (
    <div className="space-y-8 animate-in fade-in zoom-in duration-500">
      
      {/* Üst Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
            <UploadCloud className="text-indigo-400" /> XML Entegrasyonları
          </h1>
          <p className="text-slate-400 mt-1 text-sm">Tedarikçi XML linklerinizi buradan yönetin ve otomatik senkronize edin.</p>
        </div>
        <div className="flex gap-2">
           <Link 
             href="/xml-integration/new"
             className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2"
            >
             <Plus size={16} /> Yeni Entegrasyon Ekle
           </Link>
        </div>
      </div>

      {/* Liste */}
      <Card>
        <CardContent className="p-0">
          {loading ? (
             <div className="p-12 flex flex-col items-center justify-center text-slate-500">
                <div className="h-8 w-8 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin mb-4"></div>
                <p>Entegrasyonlar yükleniyor...</p>
             </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-950/30 text-xs uppercase font-semibold text-slate-500 border-b border-slate-800/50">
                  <tr>
                    <th className="px-6 py-4">XML Linki</th>
                    <th className="px-6 py-4">Durum</th>
                    <th className="px-6 py-4">Son Eşitleme</th>
                    <th className="px-6 py-4">Oluşturulma</th>
                    <th className="px-6 py-4 text-right">İşlem</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/50">
                  {integrations.length > 0 ? (
                    integrations.map((integration) => (
                      <tr key={integration.id} className="group hover:bg-slate-800/20 transition-colors">
                        <td className="px-6 py-4 font-medium text-slate-300">
                          <div className="flex items-center gap-2">
                             <span className="truncate max-w-xs" title={integration.xml_url}>{integration.xml_url}</span>
                             <a href={integration.xml_url} target="_blank" className="text-slate-500 hover:text-indigo-400"><ExternalLink size={14} /></a>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <Badge variant={integration.is_active ? 'success' : 'neutral'}>
                            {integration.is_active ? 'Aktif' : 'Pasif'}
                          </Badge>
                        </td>
                        <td className="px-6 py-4 text-slate-400">
                           {integration.last_sync_at ? new Date(integration.last_sync_at).toLocaleString() : '-'}
                        </td>
                        <td className="px-6 py-4 text-slate-500">
                           {new Date(integration.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button 
                               onClick={() => handleSyncNow(integration.id)}
                               className="p-2 hover:bg-indigo-500/10 hover:text-indigo-400 text-slate-400 rounded-lg transition-colors tooltip-trigger"
                               title="Şimdi Çalıştır"
                            >
                              <Play size={16} />
                            </button>
                            <Link 
                              href={`/xml-integration/${integration.id}`}
                              className="p-2 hover:bg-slate-800 text-slate-400 hover:text-slate-200 rounded-lg transition-colors inline-flex"
                            >
                              <Edit size={16} />
                            </Link>
                            <button 
                              onClick={() => handleDelete(integration.id)}
                              className="p-2 hover:bg-rose-500/10 hover:text-rose-400 text-slate-400 rounded-lg transition-colors"
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                        <div className="flex flex-col items-center gap-3">
                           <UploadCloud size={48} className="text-slate-700" />
                           <p>Henüz bir XML entegrasyonu eklenmemiş.</p>
                           <Link href="/xml-integration/new" className="text-indigo-400 hover:underline">İlk entegrasyonu ekle</Link>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
