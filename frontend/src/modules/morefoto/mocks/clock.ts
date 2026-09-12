import { readDemo, writeDemo } from './storage';
export const defaultDemoNow = '2026-09-07T12:00:00+03:00';
export function getDemoNow(): string {
  return readDemo('clock:now', defaultDemoNow);
}
export function setDemoNow(value: string): void {
  if (!Number.isFinite(Date.parse(value))) throw new Error('Укажите корректную демонстрационную дату.');
  writeDemo('clock:now', new Date(value).toISOString());
}
