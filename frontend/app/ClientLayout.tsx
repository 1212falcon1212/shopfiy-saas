"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Sidebar } from "./components/layout/Sidebar";
import { Header } from "./components/layout/Header";

export default function ClientLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(true);

  const isLandingPage = pathname === "/";
  const isAuthPage = pathname === "/login" || pathname === "/register";

  useEffect(() => {
    const checkAuth = () => {
      const token = localStorage.getItem("auth_token") || sessionStorage.getItem("auth_token");
      
      if (!isLandingPage && !isAuthPage && !token) {
        // Korunan sayfadaysa ve token yoksa login'e at
        router.push("/login");
      } else if (isAuthPage && token) {
        // Login/Register sayfasındaysa ve token varsa dashboard'a at
        router.push("/dashboard");
      }
      
      setIsLoading(false);
    };

    checkAuth();
  }, [pathname, isLandingPage, isAuthPage, router]);

  // Landing Page veya Auth sayfalarında Sidebar ve Header gösterme
  if (isLandingPage || isAuthPage) {
    return <>{children}</>;
  }

  // Yükleniyor durumu (Flash'ı önlemek için isteğe bağlı)
  // if (isLoading) return null; 

  return (
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
  );
}
