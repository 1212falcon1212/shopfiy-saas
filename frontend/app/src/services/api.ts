import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api', // Canlıya geçince burası değişecek
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Axios Interceptor (Araya Girici)
api.interceptors.response.use(
  (response) => {
    // Başarılı yanıtları olduğu gibi geri döndür
    return response;
  },
  (error) => {
    const { status, headers } = error.response || {};

    // Billing (Ödeme) için Shopify'dan gelen özel yönlendirme
    if (status === 401 && headers && headers['x-shopify-redirect-url']) {
      const redirectUrl = headers['x-shopify-redirect-url'];
      
      // Standart yönlendirme yerine, Shopify App Bridge ile yönlendirme yapmalıyız.
      // Bu, uygulamanın iframe içinde kalmasını sağlar.
      // Bu bilgi genellikle ana uygulama bileşeninde (örn: App.tsx) ele alınır
      // ve App Bridge'in Redirect action'ı kullanılır.
      // Şimdilik en basit yöntemle, sayfanın tamamını yönlendirelim:
      window.top.location.href = redirectUrl;

      // Promise'i reddetmek yerine, boş bir promise döndürerek 
      // zincirleme .catch() bloklarının çalışmasını engelliyoruz.
      return new Promise(() => {});
    }

    // Diğer tüm hataları olduğu gibi geri döndür
    return Promise.reject(error);
  }
);

export default api;