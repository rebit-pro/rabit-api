/** J2: the letter with the link to the paid order (order email summary, FIL-05 repeat, FIL-06 status). */
export type OrderEmailStatus = 'pending' | 'accepted' | 'failed' | 'cancelled';
export interface OrderEmailDelivery {
  id: string;
  kind: 'auto' | 'manual';
  status: OrderEmailStatus;
  recipient: string;
  createdAt: string;
  acceptedAt: string | null;
}
export interface OrderEmailSummary {
  enabled: boolean;
  recipient: string | null;
  latest: OrderEmailDelivery | null;
  canResend: boolean;
  resendAvailableAt: string | null;
}

const moscow = new Intl.DateTimeFormat('ru-RU', {
  day: 'numeric',
  month: 'long',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Europe/Moscow'
});
const time = new Intl.DateTimeFormat('ru-RU', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Moscow' });

/** What the order page says about the letter; null — the block is hidden (sending is off or the order is not paid). */
export function orderEmailText(summary: OrderEmailSummary): { text: string; tone: 'info' | 'success' | 'warning' } | null {
  if (!summary.enabled || !summary.recipient) return null;
  const latest = summary.latest?.status === 'cancelled' ? null : summary.latest;
  if (!latest) return { text: 'Можно отправить ссылку на этот заказ на ' + summary.recipient + '.', tone: 'info' };
  if (latest.status === 'accepted') {
    return {
      text:
        'Ссылка на заказ отправлена на ' +
        latest.recipient +
        (latest.acceptedAt ? ' ' + moscow.format(new Date(latest.acceptedAt)) + ' мск' : '') +
        '. Если письма нет — проверьте папку «Спам».',
      tone: 'success'
    };
  }
  if (latest.status === 'failed') return { text: 'Не удалось отправить письмо на ' + latest.recipient + '.', tone: 'warning' };
  return { text: 'Отправляем ссылку на заказ на ' + latest.recipient + '.', tone: 'info' };
}

/** The repeat button waits for the server interval (J2-DEC-07); the label names the moment it opens. */
export function resendWait(summary: OrderEmailSummary, now: number): string {
  if (!summary.resendAvailableAt) return '';
  const at = Date.parse(summary.resendAvailableAt);
  return at > now ? 'Повторить можно после ' + time.format(new Date(at)) : '';
}

const errors: Record<string, string> = {
  DELIVERY_TOO_SOON: 'Письмо уже отправлено недавно. Повторить можно чуть позже.',
  DELIVERY_LIMIT: 'Сегодня письмо отправлялось уже пять раз. Попробуйте завтра или напишите куратору.',
  DELIVERY_DISABLED: 'Отправка писем сейчас выключена. Сохраните ссылку на этой странице.',
  FILES_UNAVAILABLE: 'Срок этого заказа закончился — письмо не отправить.'
};
export function orderEmailError(problem: { network: boolean; code?: string | null }): string {
  if (problem.network) return 'Ответ сервера не получен. Обновите страницу, чтобы увидеть, ушло ли письмо.';
  return (problem.code && errors[problem.code]) || 'Не удалось отправить письмо. Попробуйте позже.';
}
