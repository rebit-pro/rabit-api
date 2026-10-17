/** J2/J5: the letters with the originals and the link to the paid order (summary, FIL-05 repeat, FIL-06 status). */
export type OrderEmailStatus = 'pending' | 'accepted' | 'failed' | 'cancelled';
export interface OrderEmailDelivery {
  id: string;
  kind: 'auto' | 'manual';
  /** accepted — every letter is with the mail server; failed — at least one letter finally failed. */
  status: OrderEmailStatus;
  recipient: string;
  createdAt: string;
  acceptedAt: string | null;
  /** Letters of the delivery; 0 until they are planned. */
  letters: number;
  acceptedLetters: number;
  /** Photos attached to the letters. */
  photos: number;
  /** Photos above the letter limit: only on the order page. */
  linkOnly: number;
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

const plural = (count: number, one: string, few: string, many: string): string => {
  const tens = count % 100;
  const units = count % 10;
  if (tens >= 11 && tens <= 14) return many;
  return units === 1 ? one : units >= 2 && units <= 4 ? few : many;
};
const lettersText = (count: number) => count + ' ' + plural(count, 'письмо', 'письма', 'писем');
/** J5-DEC-02: a photo above the letter limit (20 MB) is only named in the letters. */
const largeText = (latest: OrderEmailDelivery) =>
  latest.linkOnly > 0 ? ' ' + latest.linkOnly + ' фото больше 20 МБ — их можно скачать только на этой странице.' : '';

/** What the order page says about the letters; null — the block is hidden (sending is off or the order is not paid). */
export function orderEmailText(summary: OrderEmailSummary): { text: string; tone: 'info' | 'success' | 'warning' } | null {
  if (!summary.enabled || !summary.recipient) return null;
  const latest = summary.latest?.status === 'cancelled' ? null : summary.latest;
  if (!latest) return { text: 'Можно отправить фото и ссылку на этот заказ на ' + summary.recipient + '.', tone: 'info' };
  const several = latest.letters > 1;
  if (latest.status === 'accepted') {
    return {
      text:
        (latest.photos > 0 ? 'Фото и ссылка на заказ отправлены на ' : 'Ссылка на заказ отправлена на ') +
        latest.recipient +
        (latest.acceptedAt ? ' ' + moscow.format(new Date(latest.acceptedAt)) + ' мск' : '') +
        (several ? ' — ' + lettersText(latest.letters) + ', ' + latest.photos + ' фото' : '') +
        (several ? '. Если писем нет — проверьте папку «Спам».' : '. Если письма нет — проверьте папку «Спам».') +
        largeText(latest),
      tone: 'success'
    };
  }
  if (latest.status === 'failed') {
    return {
      text:
        (several
          ? 'Не удалось отправить ' + (latest.letters - latest.acceptedLetters) + ' из ' + latest.letters + ' писем на '
          : 'Не удалось отправить письмо на ') +
        latest.recipient +
        '.' +
        (latest.photos > 0 ? ' Фото можно скачать на этой странице.' : ''),
      tone: 'warning'
    };
  }
  // The letters are planned within a minute after the payment; until then it is not known yet what they carry.
  if (latest.letters === 0) return { text: 'Готовим письмо с заказом на ' + latest.recipient + '.', tone: 'info' };
  if (latest.photos === 0) return { text: 'Отправляем ссылку на заказ на ' + latest.recipient + '.' + largeText(latest), tone: 'info' };
  return {
    text:
      'Отправляем ' +
      latest.photos +
      ' фото на ' +
      latest.recipient +
      (several ? ' — ' + lettersText(latest.letters) : '') +
      '.' +
      largeText(latest),
    tone: 'info'
  };
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
