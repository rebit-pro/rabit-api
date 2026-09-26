import type { BuyerErrors, BuyerFields } from '../types';
export function validateBuyer(value: BuyerFields, maxAvailable: boolean): BuyerErrors {
  const errors: BuyerErrors = {};
  if (value.name.trim().length < 2 || value.name.trim().length > 100) errors.name = 'Укажите имя: от 2 до 100 символов.';
  const digits = value.phone.replace(/\D/g, '');
  if (!/^[+\d\s().-]+$/.test(value.phone) || digits.length < 10 || digits.length > 15)
    errors.phone = 'Укажите телефон: от 10 до 15 цифр, например +7 900 123-45-67.';
  if (value.email.trim().length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.email.trim()))
    errors.email = 'Укажите email в формате name@example.ru.';
  if (value.comment.length > 1000) errors.comment = 'Комментарий — не более 1000 символов.';
  if (value.receiptChannel !== 'email' && (value.receiptChannel !== 'max' || !maxAvailable))
    errors.receiptChannel = 'MAX сейчас недоступен. Выберите получение чека по email.';
  if (!value.reviewed) errors.reviewed = 'Проверьте состав и условия заказа.';
  return errors;
}
export function normalizeBuyer(value: BuyerFields): Omit<BuyerFields, 'reviewed'> {
  let digits = value.phone.replace(/\D/g, '');
  if (digits.length === 11 && digits.startsWith('8')) digits = '7' + digits.slice(1);
  return {
    name: value.name.trim(),
    phone: '+' + digits,
    email: value.email.trim().toLowerCase(),
    comment: value.comment.trim(),
    receiptChannel: value.receiptChannel
  };
}
