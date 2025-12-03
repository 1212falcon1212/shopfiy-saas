import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { SettingsProvider } from "./context/SettingsContext";
import ClientLayout from "./ClientLayout";

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
        <SettingsProvider>
          <ClientLayout>
            {children}
          </ClientLayout>
        </SettingsProvider>
      </body>
    </html>
  );
}
