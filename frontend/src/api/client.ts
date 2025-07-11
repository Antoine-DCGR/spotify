import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

export const BACKEND_URL = 'http://127.0.0.1:8000'; // ou 10.0.2.2

const client = axios.create({
  baseURL: BACKEND_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// ✅ PLUS D'INTERCEPTOR REQUEST – TOUT PASSE PAR AUTHFETCH (fetch) ou tu passes explicitement le JWT
// ❗️ Tu peux toujours ajouter le JWT dans un appel axios spécifique via
// client.get('/endpoint', { headers: { Authorization: `Bearer ${token}` } })

client.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true;
      try {
        const res = await client.get('/api/spotify/refresh');
        const data = res.data as { jwt: string };
        if (data.jwt) {
          await SecureStore.setItemAsync('spotify_jwt', data.jwt);
          client.defaults.headers.common.Authorization = `Bearer ${data.jwt}`;
          error.config.headers.Authorization = `Bearer ${data.jwt}`;
          return client.request(error.config); // retry original
        }
      } catch (refreshError) {
        console.warn('[AXIOS] Refresh échoué', refreshError);
      }
    }
    return Promise.reject(error);
  }
);

export default client;
