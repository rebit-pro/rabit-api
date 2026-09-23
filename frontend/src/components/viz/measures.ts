import type { StatusTone } from '../status/tones';

/** Russian plural form for a count: plural(5, ['группа', 'группы', 'групп']) → 'групп'. */
export function plural(count: number, forms: readonly [one: string, few: string, many: string]): string {
  const value = Math.abs(Math.trunc(count));
  const tens = value % 100;
  const units = value % 10;
  if (tens >= 11 && tens <= 14) return forms[2];
  if (units === 1) return forms[0];
  if (units >= 2 && units <= 4) return forms[1];
  return forms[2];
}

/** A number with its word, so no value is shown bare: «12 групп», «1 заказ». */
export function countLabel(count: number, forms: readonly [string, string, string]): string {
  return new Intl.NumberFormat('ru-RU').format(count) + ' ' + plural(count, forms);
}

/** Whole percent of a part; an empty whole gives 0 instead of NaN. */
export function percent(part: number, whole: number): number {
  return whole > 0 ? Math.round((part / whole) * 100) : 0;
}

export interface Countdown {
  /** Whole days left until the closing moment; negative when overdue, null when the link is not sent yet. */
  days: number | null;
  tone: StatusTone;
  label: string;
}

const DAY = 24 * 60 * 60 * 1000;

/**
 * Days until the closing moment and their tone (design plan 9.3.5): neutral beyond 7 days, info for 3–7, warning
 * under 3, danger when overdue. Until the link is sent there is nothing to count: neutral «—».
 */
export function countdown(sentAt: string | null, closesAt: string | null, now: string): Countdown {
  if (null === sentAt || null === closesAt) return { days: null, tone: 'neutral', label: '—' };
  const left = Date.parse(closesAt) - Date.parse(now);
  if (Number.isNaN(left)) return { days: null, tone: 'neutral', label: '—' };
  if (left <= 0) {
    const late = Math.floor(-left / DAY);
    if (0 === late) return { days: 0, tone: 'danger', label: 'приём закрыт' };
    return { days: -late, tone: 'danger', label: 'просрочено на ' + late + ' ' + plural(late, ['день', 'дня', 'дней']) };
  }
  const days = Math.ceil(left / DAY);
  const tone: StatusTone = days > 7 ? 'neutral' : days >= 3 ? 'info' : 'warning';
  return { days, tone, label: days === 1 ? 'остался 1 день' : 'осталось ' + days + ' ' + plural(days, ['день', 'дня', 'дней']) };
}
