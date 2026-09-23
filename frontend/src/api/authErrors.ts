import { isAxiosError } from 'axios';

/** Russian texts for machine codes of access and sign-in errors (design plan 10.1). */
const texts: Readonly<Record<string, string>> = {
  INVALID_CREDENTIALS: 'Неверный email или пароль.',
  TOKEN_EXPIRED: 'Сессия истекла. Войдите снова, чтобы продолжить.',
  SESSION_REVOKED: 'Сессия завершена: вы вошли на другом устройстве или организатор изменил доступ. Войдите снова.',
  LINK_NOT_FOUND: 'Ссылка не найдена. Проверьте, что скопировали её целиком.',
  LINK_EXPIRED: 'Срок действия ссылки истёк.',
  LINK_USED: 'Ссылка уже использована.',
  PASSWORD_WEAK: 'Пароль слишком простой: нужно не меньше 10 символов, и он не должен совпадать с email.',
  CURRENT_PASSWORD_INVALID: 'Текущий пароль указан неверно.',
  RATE_LIMITED: 'Письмо уже отправлено. Повторить можно через минуту.',
  INVITATION_NOT_AVAILABLE: 'Приглашение не нужно: сотрудник уже задал пароль или его доступ отключён.'
};

/** Machine code of an API error: `error.code`, or a message that is itself a code. */
export function apiErrorCode(cause: unknown): string | null {
  if (!isAxiosError(cause)) return null;
  const body = cause.response?.data as { error?: { code?: string; message?: string }; message?: string } | undefined;
  const candidate = body?.error?.code ?? body?.error?.message ?? body?.message;
  return typeof candidate === 'string' && /^[A-Z][A-Z_]+$/.test(candidate) ? candidate : null;
}

export function authErrorText(cause: unknown, fallback: string): string {
  const code = apiErrorCode(cause);
  if (code !== null) return texts[code] ?? fallback;
  if (isAxiosError(cause)) {
    const message = (cause.response?.data as { message?: string } | undefined)?.message;
    return typeof message === 'string' && message.trim() !== '' ? message : fallback;
  }
  return cause instanceof Error && cause.message ? cause.message : fallback;
}
