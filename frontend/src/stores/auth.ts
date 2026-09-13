import { defineStore } from 'pinia';
import { isAxiosError } from 'axios';
import { ref, computed } from 'vue';
import { router } from '@/router';
import { authApi, type AuthUser, type GeeTestCaptchaPayload, type RequestRegistrationCodeResponse } from '@/api/auth';

import { isMockApiEnabled } from '@/mocks/config';
import { requireDemoAccount } from '@/modules/morefoto/mocks/service';
import { isStaffRole } from '@/modules/morefoto/types';

const sessionPrefix = isMockApiEnabled ? 'morefoto:demo:auth:' : 'morefoto:live:auth:';
const TOKEN_STORAGE_KEY = sessionPrefix + 'token';
const USER_STORAGE_KEY = sessionPrefix + 'user';
const TOKEN_EXPIRES_AT_STORAGE_KEY = sessionPrefix + 'expires_at';

function readStoredUser(): AuthUser | null {
  const rawUser = localStorage.getItem(USER_STORAGE_KEY);

  if (null === rawUser) {
    return null;
  }

  try {
    return JSON.parse(rawUser) as AuthUser;
  } catch {
    return null;
  }
}

function resolveExpiresAtTimestamp(expiresAt: string | null): number | null {
  if (null === expiresAt) {
    return null;
  }

  const expiresAtTimestamp = Date.parse(expiresAt);

  return Number.isNaN(expiresAtTimestamp) ? null : expiresAtTimestamp;
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_STORAGE_KEY));
  const user = ref<AuthUser | null>(readStoredUser());
  const expiresAt = ref<string | null>(localStorage.getItem(TOKEN_EXPIRES_AT_STORAGE_KEY));
  const returnUrl = ref<string | null>(null);
  let sessionExpirationTimeoutId: number | null = null;
  let verifiedToken: string | null = null;
  let profileRequest: Promise<void> | null = null;

  const isAuthenticated = computed(() => {
    if (null === token.value || null === user.value) {
      return false;
    }

    // Legacy-сессия без expiresAt — считаем аутентифицированной до первого 401
    if (null === expiresAt.value) {
      return true;
    }

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp) {
      return false;
    }

    return expiresAtTimestamp > Date.now();
  });

  function clearSessionExpirationTimer(): void {
    if (null === sessionExpirationTimeoutId) {
      return;
    }

    window.clearTimeout(sessionExpirationTimeoutId);
    sessionExpirationTimeoutId = null;
  }

  function expireSession(): void {
    const currentRoute = router.currentRoute.value;
    const authRequired = currentRoute.matched.some((record) => true === record.meta['requiresAuth']);

    if (authRequired) {
      returnUrl.value = currentRoute.fullPath;
    }

    clearSession();

    if (authRequired) {
      void router.push('/login?reason=session-expired');
    }
  }

  function scheduleSessionExpiration(): void {
    clearSessionExpirationTimer();

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp) {
      return;
    }

    const expiresInMilliseconds = expiresAtTimestamp - Date.now();

    if (0 >= expiresInMilliseconds) {
      expireSession();
      return;
    }

    sessionExpirationTimeoutId = window.setTimeout(() => {
      expireSession();
    }, expiresInMilliseconds);
  }

  function restoreSession(): void {
    if (null === token.value && null === user.value && null === expiresAt.value) {
      clearSessionExpirationTimer();
      return;
    }

    if (null === token.value || null === user.value) {
      clearSession();
      return;
    }

    // Старая сессия без expiresAt — оставляем до первого 401, не разлогиниваем
    if (null === expiresAt.value) {
      return;
    }

    const expiresAtTimestamp = resolveExpiresAtTimestamp(expiresAt.value);

    if (null === expiresAtTimestamp || expiresAtTimestamp <= Date.now()) {
      clearSession();
      return;
    }

    if (isMockApiEnabled && token.value.startsWith('morefoto-demo-') && isStaffRole(user.value.role)) {
      try {
        const account = requireDemoAccount(token.value);
        user.value = { id: account.id, name: account.name, email: account.email, role: account.role };
      } catch {
        clearSession();
        return;
      }
    }
    scheduleSessionExpiration();
  }

  function setSession(newToken: string, newUser: AuthUser, newExpiresAt: string): void {
    const expiresAtTimestamp = resolveExpiresAtTimestamp(newExpiresAt);

    if (null === expiresAtTimestamp || expiresAtTimestamp <= Date.now()) {
      clearSession();
      throw new Error('Получен некорректный или истёкший токен.');
    }

    token.value = newToken;
    user.value = newUser;
    expiresAt.value = newExpiresAt;
    localStorage.setItem(TOKEN_STORAGE_KEY, newToken);
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(newUser));
    localStorage.setItem(TOKEN_EXPIRES_AT_STORAGE_KEY, newExpiresAt);
    scheduleSessionExpiration();
  }

  function clearSession(): void {
    clearSessionExpirationTimer();
    verifiedToken = null;
    token.value = null;
    user.value = null;
    expiresAt.value = null;
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(TOKEN_EXPIRES_AT_STORAGE_KEY);
  }

  function getAccessToken(): string | null {
    return isAuthenticated.value ? token.value : null;
  }

  const homePath = computed(() => {
    if (!isStaffRole(user.value?.role)) return '/access-unavailable';
    if (isMockApiEnabled) return '/cabinet/overview';
    return user.value?.permissions?.includes('catalog.manage') ? '/cabinet/catalog' : '/cabinet/profile';
  });

  async function ensureProfile(): Promise<void> {
    if (isMockApiEnabled || !isAuthenticated.value || verifiedToken === token.value) return;
    if (profileRequest) return profileRequest;
    const currentToken = token.value;
    profileRequest = (async () => {
      try {
        const profile = await authApi.me();
        if (token.value !== currentToken) return;
        if (!profile.active || !isStaffRole(profile.role)) throw new Error('Доступ сотрудника не назначен.');
        user.value = profile;
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(profile));
        verifiedToken = currentToken;
      } catch (cause) {
        if (token.value !== currentToken) return;
        if (isAxiosError(cause) && cause.response?.status === 403 && user.value) {
          user.value = { id: user.value.id, name: user.value.name, email: user.value.email };
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user.value));
          verifiedToken = currentToken;
          return;
        }
        clearSession();
        throw cause;
      } finally {
        profileRequest = null;
      }
    })();
    return profileRequest;
  }

  async function login(email: string, password: string, captcha?: GeeTestCaptchaPayload): Promise<void> {
    const response = await authApi.login({ email, password, captcha });
    setSession(response.token, response.user, response.expiresAt);
    await ensureProfile();
    const fallback = homePath.value;
    const candidate = returnUrl.value;
    returnUrl.value = null;
    const resolved = candidate?.startsWith('/') && !candidate.startsWith('//') ? router.resolve(candidate) : null;
    const role = user.value?.role;
    const allowed =
      resolved?.meta.requiresAuth && isStaffRole(role) && (!resolved.meta.staffRoles || resolved.meta.staffRoles.includes(role));
    await router.push(allowed && candidate ? candidate : fallback);
  }

  async function requestRegistrationCode(email: string, password: string): Promise<RequestRegistrationCodeResponse> {
    return authApi.requestRegistrationCode({ email, password });
  }

  async function confirmRegistration(email: string, code: string): Promise<void> {
    const response = await authApi.confirmRegistration({ email, code });
    setSession(response.token, response.user, response.expiresAt);
    await router.push('/cabinet/overview');
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout();
    } catch {
      // Даже если запрос не прошёл — чистим сессию
    }
    clearSession();
    returnUrl.value = null;
    await router.push('/login');
  }

  restoreSession();

  return {
    token,
    homePath,
    ensureProfile,
    user,
    expiresAt,
    returnUrl,
    isAuthenticated,
    clearSession,
    restoreSession,
    getAccessToken,
    login,
    requestRegistrationCode,
    confirmRegistration,
    logout
  };
});
