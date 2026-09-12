import { availablePhotos, eligiblePhotos, lateDecision } from '../../settlement/rules.ts';
import type { OrderSnapshot } from '../types.js';
import type { SupportDraft, SupportErrors } from './types.js';
export const supportTopics = [
  { value: 'extension', title: 'Продлить приём заказов' },
  { value: 'files', title: 'Фотографии и скачивание' },
  { value: 'receipt', title: 'Чек или письмо не пришли' },
  { value: 'payment', title: 'Вопрос об оплате' },
  { value: 'correction', title: 'Исправление или возврат' },
  { value: 'other', title: 'Другой вопрос' }
] as const;

// Calendar month in Moscow, including dates that do not exist in the following month.
export function downloadDeadline(paidAt: string): string {
  const offset = 3 * 60 * 60 * 1000;
  const date = new Date(Date.parse(paidAt) + offset);
  if (!Number.isFinite(date.getTime())) throw new Error('Дата покупки недоступна. Обратитесь к куратору.');
  const day = date.getUTCDate();
  date.setUTCDate(1);
  date.setUTCMonth(date.getUTCMonth() + 1);
  const last = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
  date.setUTCDate(Math.min(day, last));
  return new Date(date.getTime() - offset).toISOString();
}
export function downloadAccess(order: OrderSnapshot, now: string) {
  if (order.paymentStatus !== 'paid' || !order.paidAt) return { state: 'unpaid' as const, deadline: null };
  const deadline = downloadDeadline(order.paidAt);
  if (order.latePayment && lateDecision(order) !== 'fulfil')
    return { state: lateDecision(order) === 'refund' ? ('refund' as const) : ('review' as const), deadline };
  if (eligiblePhotos(order).length && !availablePhotos(order).length) return { state: 'revoked' as const, deadline };
  if (!availablePhotos(order).length) return { state: 'empty' as const, deadline };
  if (!Number.isFinite(Date.parse(now)) || Date.parse(now) < Date.parse(order.paidAt)) return { state: 'unpaid' as const, deadline };
  return {
    state: Date.parse(now) >= Date.parse(deadline) ? ('expired' as const) : ('available' as const),
    deadline
  };
}
export function supportPhotos(order: OrderSnapshot) {
  const photos = [...order.digitalPhotos, ...order.quote.lines.flatMap((line) => (line.photo ? [line.photo] : []))];
  return photos.filter((photo, index) => photos.findIndex((item) => item.id === photo.id) === index);
}
export function validateSupport(draft: SupportDraft, order: OrderSnapshot): SupportErrors {
  const errors: SupportErrors = {};
  if (!supportTopics.some((topic) => topic.value === draft.topic)) errors.topic = 'Выберите тему обращения.';
  if (draft.photoId && !supportPhotos(order).some((photo) => photo.id === draft.photoId))
    errors.photoId = 'Выберите фотографию из этого заказа.';
  if (draft.replyEmail.trim().length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(draft.replyEmail.trim()))
    errors.replyEmail = 'Укажите email для ответа в формате name@example.ru.';
  if (draft.message.trim().length < 10 || draft.message.trim().length > 2000) errors.message = 'Опишите вопрос: от 10 до 2000 символов.';
  return errors;
}
