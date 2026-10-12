import type { BuyerErrors, BuyerFields } from '../types';
export function validateBuyer(value: BuyerFields, maxAvailable: boolean): BuyerErrors {
  const errors: BuyerErrors = {};
  if (value.email.trim().length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.email.trim()))
    errors.email = 'Укажите email в формате name@example.ru.';
  if (value.comment.length > 1000) errors.comment = 'Комментарий — не более 1000 символов.';
  if (value.receiptChannel !== 'email' && (value.receiptChannel !== 'max' || !maxAvailable))
    errors.receiptChannel = 'MAX сейчас недоступен. Выберите получение чека по email.';
  if (!value.reviewed) errors.reviewed = 'Проверьте состав и условия заказа.';
  return errors;
}
export function normalizeBuyer(value: BuyerFields): Omit<BuyerFields, 'reviewed'> {
  return {
    email: value.email.trim().toLowerCase(),
    comment: value.comment.trim(),
    receiptChannel: value.receiptChannel
  };
}
