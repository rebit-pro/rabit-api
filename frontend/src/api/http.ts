import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { router } from '@/router';
import { moreFotoMockAdapter } from '@/modules/morefoto/mocks/adapter';
import { isMockApiEnabled } from '@/mocks/config';
import { apiErrorCode } from './authErrors';
import { sessionEndReason } from './sessionEnd';

declare module 'axios' {
  interface AxiosRequestConfig {
    unwrapEnvelope?: boolean;
  }
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL?.replace(/\/api\/?$/, '') || undefined,
  adapter: isMockApiEnabled ? moreFotoMockAdapter : undefined,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  },
  timeout: 15000
});

// Request: подставляем JWT-токен
api.interceptors.request.use((config) => {
  const auth = useAuthStore();
  const token = auth.getAccessToken();

  if (null !== token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// Response: обработка 401
api.interceptors.response.use(
  (response) => {
    // Разворачиваем обёртку API: { data: { ... } } → { ... }
    if (response.config.unwrapEnvelope !== false && response.data?.data !== undefined) {
      response.data = response.data.data;
    }
    return response;
  },
  async (error) => {
    if (error.response?.data?.error?.message !== undefined) {
      error.response.data.message = error.response.data.error.message;
    }

    if (error.response?.status === 401) {
      const auth = useAuthStore();
      // A replaced or revoked session is not the same as an expired one: the sign-in page explains which happened.
      const reason = sessionEndReason(apiErrorCode(error), auth.expiresAt);
      auth.clearSession();
      if (!error.config?.url?.includes('/auth/login')) {
        const current = router.currentRoute.value;
        if (current.meta.requiresAuth) auth.returnUrl = current.fullPath;
        void router.replace('/login?reason=' + reason);
      }
    }
    return Promise.reject(error);
  }
);

export default api;
