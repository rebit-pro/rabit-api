import { parseTransmission } from '../handoff/rules.ts';
export interface ReviewSettings {
  date: string;
  delay: number;
  offline: boolean;
}
export function reviewTime(value: string): string | null {
  return parseTransmission(value, '9999-12-31T23:59:59Z');
}
export function reviewSettingsError(value: ReviewSettings): string {
  if (!reviewTime(value.date)) return 'Укажите существующую дату и время в МСК.';
  if (![0, 250, 1500, 3000].includes(value.delay)) return 'Выберите задержку из списка.';
  return '';
}
export function hasDemoData(keys: string[]): boolean {
  return keys.some((k) => k.startsWith('morefoto:demo:'));
}
