"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import api from "../src/services/api";

export default function LoginPage() {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setIsLoading(true);
    setError(null);

    const formData = new FormData(e.currentTarget);
    const data = Object.fromEntries(formData.entries());

    try {
      const response = await api.post('/login', data);
      
      if (response.data.access_token) {
        // Token'ı kaydet
        localStorage.setItem('auth_token', response.data.access_token);
        // Dashboard'a yönlendir
        router.push("/dashboard");
      }
    } catch (err: any) {
      console.error('Giriş hatası:', err);
      setError(err.response?.data?.message || "Giriş yapılırken bir hata oluştu. Lütfen bilgilerinizi kontrol edin.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#0B1120] p-4">
      <div className="w-full max-w-md space-y-8 bg-[#0F1623] p-8 rounded-2xl border border-slate-800 shadow-2xl shadow-indigo-500/10">
        <div className="text-center">
          <h2 className="text-3xl font-bold text-white">Tekrar Hoşgeldiniz</h2>
          <p className="mt-2 text-sm text-slate-400">Hesabınıza giriş yapın</p>
        </div>

        {error && (
          <div className="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-4 py-3 rounded-lg text-sm">
            {error}
          </div>
        )}

        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          <div className="space-y-4">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-slate-300">
                E-posta
              </label>
              <input
                id="email"
                name="email"
                type="email"
                required
                className="mt-1 block w-full rounded-lg bg-[#0B1120] border border-slate-700 text-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500 transition-colors outline-none"
                placeholder="ornek@sirket.com"
              />
            </div>
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-slate-300">
                Şifre
              </label>
              <input
                id="password"
                name="password"
                type="password"
                required
                className="mt-1 block w-full rounded-lg bg-[#0B1120] border border-slate-700 text-white px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500 transition-colors outline-none"
                placeholder="••••••••"
              />
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <input
                id="remember-me"
                name="remember-me"
                type="checkbox"
                className="h-4 w-4 rounded border-slate-700 bg-[#0B1120] text-indigo-600 focus:ring-indigo-500"
              />
              <label htmlFor="remember-me" className="ml-2 block text-sm text-slate-400">
                Beni hatırla
              </label>
            </div>

            <div className="text-sm">
              <Link href="#" className="font-medium text-indigo-400 hover:text-indigo-300">
                Şifremi unuttum
              </Link>
            </div>
          </div>

          <button
            type="submit"
            disabled={isLoading}
            className="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? "Giriş yapılıyor..." : "Giriş Yap"}
          </button>
        </form>

        <div className="text-center text-sm">
          <span className="text-slate-400">Hesabınız yok mu? </span>
          <Link href="/register" className="font-medium text-indigo-400 hover:text-indigo-300">
            Kayıt Ol
          </Link>
        </div>
      </div>
    </div>
  );
}
