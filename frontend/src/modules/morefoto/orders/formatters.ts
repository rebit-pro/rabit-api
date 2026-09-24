export function formatMoment(value: string): string {
  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'long', timeStyle: 'short', timeZone: 'Europe/Moscow' }).format(new Date(value));
}
export const paymentLabels = {
  unpaid: 'Не оплачено',
  pending: 'Ожидает подтверждения',
  declined: 'Оплата отклонена',
  paid: 'Оплачено · демонстрация'
};
/** Live screens show a real payment without the demo mark (design plan 10.5). */
export const livePaymentLabels: Record<keyof typeof paymentLabels, string> = { ...paymentLabels, paid: 'Оплачено' };
export const productionLabels = {
  'not-started': 'Не начато',
  queued: 'В очереди',
  printing: 'В печати',
  ready: 'Готово',
  delivered: 'Передано в учреждение'
};
