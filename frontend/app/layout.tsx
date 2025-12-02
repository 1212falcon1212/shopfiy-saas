import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { Sidebar } from "./components/layout/Sidebar";
import { Header } from "./components/layout/Header";

const inter = Inter({ subsets: ["latin"] });

export const metadata: Metadata = {
  title: "Shopify Panel",
  description: "Modern E-ticaret Yönetim Paneli",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="tr" className="dark">
      <body className={`${inter.className} bg-[#0B1120] text-slate-200 antialiased selection:bg-indigo-500/30 selection:text-indigo-200`}>
        <div className="flex h-screen overflow-hidden">
          {/* Sol Sidebar */}
          <Sidebar />

          {/* Sağ İçerik Alanı */}
          <main className="flex-1 flex flex-col min-w-0 overflow-hidden bg-[#0B1120] relative">
             
             {/* Arka plan efektleri */}
             <div className="absolute top-0 left-0 w-full h-96 bg-indigo-500/5 rounded-full blur-3xl -translate-y-1/2 pointer-events-none" />
             <div className="absolute bottom-0 right-0 w-96 h-96 bg-emerald-500/5 rounded-full blur-3xl translate-y-1/2 pointer-events-none" />

             <Header />
            
            <div className="flex-1 overflow-y-auto p-4 md:p-8 relative z-10 scroll-smooth">
              <div className="max-w-7xl mx-auto space-y-8 pb-10">
                {children}
              </div>
            </div>
          </main>
        </div>
      </body>
    </html>
  );
}
