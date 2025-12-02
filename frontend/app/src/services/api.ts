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
      
      // DÜZELTME: window.top'un varlığını kontrol ediyoruz
      if (window.top) {
          window.top.location.href = redirectUrl;
      } else {
          // Eğer window.top yoksa (çok nadir), normal pencereyi yönlendir
          window.location.href = redirectUrl;
      }

      return new Promise(() => {});
    }

    // Diğer tüm hataları olduğu gibi geri döndür
    return Promise.reject(error);
  }
);

export default api;