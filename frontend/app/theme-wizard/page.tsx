'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation'; // Yönlendirme için
import { Card } from '../components/ui/Card';
import { api } from '../src/services/api'; 
import { Wand2, Figma, Check, Loader2, ArrowRight, DownloadCloud } from 'lucide-react';

export default function ThemeWizardPage() {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<'ai' | 'figma'>('ai');
  
  // --- AI Form Data ---
  const [formData, setFormData] = useState({
    storeName: '',
    industry: 'fashion',
    style: 'modern',
    primaryColor: '#000000',
    features: {
      newsletter: true,
      blog: false,
      testimonials: true,
      instagramFeed: false,
    }
  });

  // --- Figma Form Data ---
  const [figmaUrl, setFigmaUrl] = useState('');
  const [figmaAnalysis, setFigmaAnalysis] = useState<any>(null);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isCreatingTheme, setIsCreatingTheme] = useState(false); // Tema oluşturma durumu

  const industries = [
    { id: 'fashion', name: 'Moda & Giyim', icon: '👕' },
    { id: 'electronics', name: 'Elektronik', icon: '🔌' },
    { id: 'food', name: 'Gıda & İçecek', icon: '🍔' },
    { id: 'home', name: 'Ev & Yaşam', icon: '🏠' },
    { id: 'beauty', name: 'Kozmetik', icon: '💄' },
    { id: 'other', name: 'Diğer', icon: '📦' },
  ];

  const styles = [
    { id: 'modern', name: 'Modern & Minimal', desc: 'Temiz çizgiler, bol beyaz alan' },
    { id: 'bold', name: 'Cesur & Renkli', desc: 'Canlı renkler, büyük tipografi' },
    { id: 'luxury', name: 'Lüks & Zarif', desc: 'Serif fontlar, altın/siyah tonlar' },
    { id: 'warm', name: 'Sıcak & Doğal', desc: 'Toprak tonları, yumuşak köşeler' },
  ];

  // --- AI Logic ---
  const generateLovableUrl = () => {
    const prompt = `Create a complete e-commerce store design for a ${formData.industry} brand named "${formData.storeName}". 
    Style should be ${formData.style}. 
    Primary color is ${formData.primaryColor}. 
    
    Include these sections:
    - Hero section with clear CTA
    - Featured products grid
    ${formData.features.newsletter ? '- Newsletter signup' : ''}
    ${formData.features.blog ? '- Blog posts section' : ''}
    ${formData.features.testimonials ? '- Customer testimonials' : ''}
    ${formData.features.instagramFeed ? '- Instagram feed gallery' : ''}
    
    The design should be responsive, user-friendly, and optimized for conversions. Use modern UI components with Tailwind CSS.`;

    const encodedPrompt = encodeURIComponent(prompt);
    return `https://lovable.dev/?autosubmit=true#prompt=${encodedPrompt}`;
  };

  const handleGenerateAI = () => {
    if (!formData.storeName) {
      alert('Lütfen bir mağaza adı giriniz.');
      return;
    }
    const url = generateLovableUrl();
    window.open(url, '_blank');
  };

  // --- Figma Logic ---
  const handleAnalyzeFigma = async () => {
    if (!figmaUrl) {
      alert('Lütfen bir Figma dosya URL\'i giriniz.');
      return;
    }

    setIsAnalyzing(true);
    setFigmaAnalysis(null);

    try {
      // API isteği
      const response = await fetch('http://localhost:8000/api/figma/analyze', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ file_url: figmaUrl }),
      });
      
      const data = await response.json();
      
      if (data.error) {
        alert(data.error);
      } else {
        setFigmaAnalysis(data);
      }
    } catch (error) {
      console.error('Figma Error:', error);
      alert('Figma analizi sırasında bir hata oluştu.');
    } finally {
      setIsAnalyzing(false);
    }
  };

  // Tema Oluşturma (Simülasyon)
  const handleCreateTheme = () => {
    setIsCreatingTheme(true);

    // 3 Saniye sonra "Başarılı" de ve yönlendir
    setTimeout(() => {
      setIsCreatingTheme(false);
      alert(`"${figmaAnalysis.file_name}" başarıyla tema olarak oluşturuldu ve panelinize eklendi!`);
      router.push('/'); // Dashboard'a yönlendir
    }, 3000);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Tasarım Merkezi 🎨</h1>
          <p className="text-gray-500 text-sm">Yapay zeka ile oluşturun veya Figma'dan aktarın.</p>
        </div>
        
        {/* Tabs */}
        <div className="flex bg-white p-1 rounded-lg border shadow-sm">
          <button
            onClick={() => setActiveTab('ai')}
            className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all
              ${activeTab === 'ai' 
                ? 'bg-blue-50 text-blue-600 shadow-sm' 
                : 'text-gray-500 hover:bg-gray-50'}`}
          >
            <Wand2 size={16} />
            AI Sihirbazı
          </button>
          <button
            onClick={() => setActiveTab('figma')}
            className={`flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all
              ${activeTab === 'figma' 
                ? 'bg-purple-50 text-purple-600 shadow-sm' 
                : 'text-gray-500 hover:bg-gray-50'}`}
          >
            <Figma size={16} />
            Figma Import
          </button>
        </div>
      </div>

      {/* --- AI TAB CONTENT --- */}
      {activeTab === 'ai' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
          {/* Sol Kolon: Form */}
          <div className="space-y-6">
            <Card className="p-6">
              <h2 className="text-lg font-semibold mb-4">Mağaza Bilgileri</h2>
              
              {/* Mağaza Adı */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Mağaza Adı
                </label>
                <input
                  type="text"
                  value={formData.storeName}
                  onChange={(e) => setFormData({...formData, storeName: e.target.value})}
                  placeholder="Örn: Modam Butik"
                  className="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>

              {/* Sektör Seçimi */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Sektör
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  {industries.map((ind) => (
                    <button
                      key={ind.id}
                      onClick={() => setFormData({...formData, industry: ind.id})}
                      className={`p-2 rounded-lg border text-sm flex items-center justify-center gap-2 transition-colors
                        ${formData.industry === ind.id 
                          ? 'bg-blue-50 border-blue-500 text-blue-700' 
                          : 'hover:bg-gray-50 border-gray-200'}`}
                    >
                      <span>{ind.icon}</span>
                      {ind.name}
                    </button>
                  ))}
                </div>
              </div>

              {/* Stil Seçimi */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Tasarım Stili
                </label>
                <div className="space-y-2">
                  {styles.map((style) => (
                    <button
                      key={style.id}
                      onClick={() => setFormData({...formData, style: style.id})}
                      className={`w-full p-3 rounded-lg border text-left transition-colors
                        ${formData.style === style.id 
                          ? 'bg-blue-50 border-blue-500 ring-1 ring-blue-500' 
                          : 'hover:bg-gray-50 border-gray-200'}`}
                    >
                      <div className="font-medium text-gray-900">{style.name}</div>
                      <div className="text-xs text-gray-500">{style.desc}</div>
                    </button>
                  ))}
                </div>
              </div>

              {/* Renk Seçimi */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Ana Renk
                </label>
                <div className="flex gap-2 items-center">
                  <input
                    type="color"
                    value={formData.primaryColor}
                    onChange={(e) => setFormData({...formData, primaryColor: e.target.value})}
                    className="h-10 w-20 rounded cursor-pointer"
                  />
                  <span className="text-sm text-gray-500">{formData.primaryColor}</span>
                </div>
              </div>
            </Card>

            <Card className="p-6">
              <h2 className="text-lg font-semibold mb-4">Özellikler</h2>
              <div className="space-y-2">
                {Object.entries(formData.features).map(([key, value]) => (
                  <label key={key} className="flex items-center gap-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                    <input
                      type="checkbox"
                      checked={value}
                      onChange={(e) => setFormData({
                        ...formData, 
                        features: {...formData.features, [key]: e.target.checked}
                      })}
                      className="w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
                    />
                    <span className="text-gray-700 capitalize">
                      {key.replace(/([A-Z])/g, ' $1').trim()}
                    </span>
                  </label>
                ))}
              </div>
            </Card>
          </div>

          {/* Sağ Kolon: AI Önizleme */}
          <div className="space-y-6">
            <div className="sticky top-6">
              <Card className="p-6 bg-gradient-to-br from-slate-900 to-slate-800 text-white border-none relative overflow-hidden">
                <div className="absolute top-0 right-0 p-4 opacity-10">
                   <Wand2 size={120} />
                </div>
                
                <h3 className="text-xl font-bold mb-2 relative z-10">AI ile Oluştur</h3>
                <p className="text-slate-300 mb-6 text-sm relative z-10">
                  Seçtiğiniz özelliklere göre Lovable AI sizin için benzersiz bir e-ticaret teması tasarlayacak.
                </p>
                
                <div className="bg-white/10 rounded-lg p-4 mb-6 text-sm relative z-10 backdrop-blur-sm">
                  <div className="text-slate-400 text-xs mb-1">Prompt Önizlemesi:</div>
                  <div className="font-mono text-slate-200 line-clamp-4">
                    Create a {formData.style} e-commerce store for {formData.storeName} ({formData.industry})...
                  </div>
                </div>

                <button
                  onClick={handleGenerateAI}
                  className="w-full py-4 bg-blue-500 hover:bg-blue-400 text-white rounded-xl font-bold text-lg shadow-lg shadow-blue-500/30 transition-all transform hover:scale-[1.02] flex items-center justify-center gap-2 relative z-10"
                >
                  <span>✨</span>
                  Tasarımı Başlat
                  <span className="text-xs font-normal opacity-75">(Lovable)</span>
                </button>
              </Card>

              <div className="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 className="font-semibold text-yellow-800 mb-1 text-sm">Nasıl Çalışır?</h4>
                <ol className="list-decimal list-inside text-sm text-yellow-700 space-y-1">
                  <li>Butona tıkladığınızda Lovable açılır.</li>
                  <li>AI, verdiğiniz ayarlarla tasarımı çizer.</li>
                  <li>Tasarımı beğendiğinizde kodları alıp panele aktarabilirsiniz.</li>
                </ol>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* --- FIGMA TAB CONTENT --- */}
      {activeTab === 'figma' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <div className="space-y-6">
            <Card className="p-6">
              <div className="flex items-center gap-3 mb-6">
                <div className="p-3 bg-purple-100 text-purple-600 rounded-lg">
                  <Figma size={24} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold">Figma Dosyası Bağla</h2>
                  <p className="text-sm text-gray-500">Figma tasarımınızı analiz edip temaya dönüştürün.</p>
                </div>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Figma Dosya URL'i
                  </label>
                  <input
                    type="text"
                    value={figmaUrl}
                    onChange={(e) => setFigmaUrl(e.target.value)}
                    placeholder="https://www.figma.com/file/..."
                    className="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 outline-none font-mono text-sm"
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    Dosya linkini kopyalayıp buraya yapıştırın. Dosyanın "Anyone with the link can view" olduğundan emin olun.
                  </p>
                </div>

                <button
                  onClick={handleAnalyzeFigma}
                  disabled={isAnalyzing || !figmaUrl}
                  className="w-full py-3 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-300 text-white rounded-lg font-semibold transition-colors flex items-center justify-center gap-2"
                >
                  {isAnalyzing ? (
                    <>
                      <Loader2 size={18} className="animate-spin" />
                      Analiz Ediliyor...
                    </>
                  ) : (
                    <>
                      Analiz Et & Dönüştür
                      <ArrowRight size={18} />
                    </>
                  )}
                </button>
              </div>
            </Card>

            {/* Sonuç Alanı */}
            {figmaAnalysis && (
              <Card className="p-6 border-l-4 border-l-green-500">
                <div className="flex items-center gap-2 text-green-600 mb-4">
                  <Check size={20} />
                  <h3 className="font-semibold">Analiz Tamamlandı</h3>
                </div>
                
                <div className="space-y-4">
                  <div>
                    <div className="text-sm text-gray-500">Dosya Adı</div>
                    <div className="font-medium">{figmaAnalysis.file_name}</div>
                  </div>
                  
                  <div>
                    <div className="text-sm text-gray-500 mb-2">Bulunan Renkler</div>
                    <div className="flex flex-wrap gap-2">
                      {Array.isArray(figmaAnalysis.tokens?.colors) && figmaAnalysis.tokens.colors.map((color: string, i: number) => (
                        <div key={i} className="group relative">
                          <div 
                            className="w-8 h-8 rounded-full border shadow-sm"
                            style={{ backgroundColor: color }}
                          />
                          <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                            {color}
                          </span>
                        </div>
                      ))}
                      {(!Array.isArray(figmaAnalysis.tokens?.colors) || figmaAnalysis.tokens.colors.length === 0) && (
                         <span className="text-sm text-gray-400 italic">Renk bulunamadı</span>
                      )}
                    </div>
                  </div>

                  <div>
                    <div className="text-sm text-gray-500 mb-2">Bulunan Fontlar</div>
                    <div className="flex flex-wrap gap-2">
                      {Array.isArray(figmaAnalysis.tokens?.fonts) && figmaAnalysis.tokens.fonts.map((font: string, i: number) => (
                        <span key={i} className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-md font-medium border">
                          {font}
                        </span>
                      ))}
                       {(!Array.isArray(figmaAnalysis.tokens?.fonts) || figmaAnalysis.tokens.fonts.length === 0) && (
                         <span className="text-sm text-gray-400 italic">Font bulunamadı</span>
                      )}
                    </div>
                  </div>
                  
                  <div className="pt-4 border-t">
                     <button 
                       onClick={handleCreateTheme}
                       disabled={isCreatingTheme}
                       className="w-full py-3 border border-purple-600 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                     >
                        {isCreatingTheme ? (
                          <>
                             <Loader2 size={16} className="animate-spin" />
                             Tema Oluşturuluyor...
                          </>
                        ) : (
                          <>
                             <DownloadCloud size={16} />
                             Temayı Oluştur
                          </>
                        )}
                     </button>
                  </div>
                </div>
              </Card>
            )}
          </div>

          {/* Sağ Kolon: Figma Bilgilendirme */}
          <div className="space-y-6">
            <Card className="p-6 bg-gradient-to-br from-purple-900 to-indigo-900 text-white border-none relative overflow-hidden">
               <div className="absolute -right-10 -bottom-10 opacity-20">
                  <Figma size={200} />
               </div>
               <h3 className="text-xl font-bold mb-4 relative z-10">Figma Entegrasyonu</h3>
               <p className="text-purple-200 mb-4 relative z-10">
                 Tasarımcılarınızın Figma'da hazırladığı arayüzleri doğrudan sisteme aktarın.
               </p>
               <ul className="space-y-3 text-sm text-purple-100 relative z-10">
                 <li className="flex items-center gap-2">
                   <Check size={16} className="text-green-400" />
                   Otomatik Renk & Font Çıkarma
                 </li>
                 <li className="flex items-center gap-2">
                   <Check size={16} className="text-green-400" />
                   Layer &rarr; HTML Dönüştürme (Beta)
                 </li>
                 <li className="flex items-center gap-2">
                   <Check size={16} className="text-green-400" />
                   Varlık (Asset) Optimizasyonu
                 </li>
               </ul>
            </Card>

            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
              <strong>İpucu:</strong> Figma dosyanızdaki "Auto Layout" özelliklerini doğru kullanırsanız, HTML dönüşümü çok daha başarılı olur.
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
