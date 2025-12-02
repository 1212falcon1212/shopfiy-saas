"use client";

import { useState, useRef } from "react";
import api from "../../src/services/api";
import { UploadCloud, X, Check, Image as ImageIcon } from "lucide-react";

interface ImageUploadProps {
  onUpload: (url: string) => void;
  currentImage?: string;
}

export function ImageUpload({ onUpload, currentImage }: ImageUploadProps) {
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState<string | null>(currentImage || null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);

    const formData = new FormData();
    formData.append("file", file);

    try {
      const res = await api.post("/upload", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      
      const uploadedUrl = res.data.url;
      setPreview(uploadedUrl);
      onUpload(uploadedUrl); // URL'i üst bileşene gönder
      
    } catch (err) {
      console.error("Resim yükleme hatası:", err);
      alert("Resim yüklenirken bir sorun oluştu.");
    } finally {
      setUploading(false);
    }
  };

  const handleRemove = () => {
    setPreview(null);
    onUpload(""); // URL'i temizle
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  return (
    <div className="w-full">
      <input
        type="file"
        ref={fileInputRef}
        onChange={handleFileChange}
        className="hidden"
        accept="image/*"
      />

      {!preview ? (
        <div 
          onClick={() => fileInputRef.current?.click()}
          className={`border-2 border-dashed border-slate-700 rounded-xl p-8 flex flex-col items-center justify-center cursor-pointer hover:border-indigo-500 hover:bg-slate-900/50 transition-all ${uploading ? 'opacity-50 pointer-events-none' : ''}`}
        >
          {uploading ? (
             <div className="h-8 w-8 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin mb-3"></div>
          ) : (
             <UploadCloud className="text-slate-500 mb-3" size={32} />
          )}
          <p className="text-sm text-slate-300 font-medium">
            {uploading ? "Yükleniyor..." : "Resim Yüklemek İçin Tıklayın"}
          </p>
          <p className="text-xs text-slate-500 mt-1">PNG, JPG, WEBP (Max 2MB)</p>
        </div>
      ) : (
        <div className="relative group rounded-xl overflow-hidden border border-slate-700 bg-slate-900">
           <img src={preview} alt="Preview" className="w-full h-48 object-cover opacity-80 group-hover:opacity-100 transition-opacity" />
           
           <div className="absolute top-2 right-2 flex gap-2">
              <button 
                onClick={handleRemove}
                className="p-1.5 bg-rose-500/90 text-white rounded-lg shadow-lg hover:bg-rose-600 transition-colors"
                title="Kaldır"
              >
                <X size={14} />
              </button>
           </div>
           
           <div className="absolute bottom-2 left-2 px-2 py-1 bg-black/60 rounded text-xs text-white flex items-center gap-1">
              <Check size={10} className="text-emerald-400" /> Yüklendi
           </div>
        </div>
      )}
    </div>
  );
}

