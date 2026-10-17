import type { ApiProblem } from './types';
import type { HandoverBlock, PaymentMode, PrintHandovers, Receipt, ReceiptKind, ReceiptStatus } from './receipt-types';

export const receiptKindTitles: Record<ReceiptKind, string> = { payment: 'Чек оплаты', closing: 'Закрывающий чек' };
export const receiptStatusLabels: Record<ReceiptStatus, string> = {
  pending: 'Формируется',
  sent: 'Отправлен на e-mail',
  failed: 'Не сформирован'
};
export const receiptTones: Record<ReceiptStatus, 'pending' | 'success' | 'danger'> = {
  pending: 'pending',
  sent: 'success',
  failed: 'danger'
};
/** Short state of the payment receipt in the registry row. */
export const receiptShortLabels: Record<ReceiptStatus, string> = {
  pending: 'Чек формируется',
  sent: 'Чек отправлен',
  failed: 'Чек не сформирован'
};
export const paymentModeLabels: Record<PaymentMode, string> = { full_prepayment: 'Предоплата 100%', full_payment: 'Полный расчёт' };

/** What the buyer is told under the receipts: where they come from and how to check them. */
export function receiptsHint(enabled: boolean, receipts: Receipt[]): string | null {
  if (receipts.length) return 'Чеки присылает ЮKassa на e-mail заказа. По реквизитам чек можно найти в приложении ФНС «Проверка чеков».';
  return enabled ? 'Чек придёт на e-mail заказа после подтверждения оплаты.' : null;
}

/** The explanation of a receipt in its state; a sent payment receipt of print says the closing one follows the handover. */
export function receiptText(receipt: Receipt): string {
  if (receipt.status === 'failed') return 'Чек не сформирован. Мы проверим и пришлём его на e-mail заказа.';
  if (receipt.status === 'pending')
    return receipt.kind === 'closing' ? 'Печать выдана — закрывающий чек формируется.' : 'Чек формируется, обычно это минута.';
  const prepaid = receipt.items.some((item) => item.paymentMode === 'full_prepayment');
  return receipt.kind === 'closing'
    ? 'Полный расчёт за выданную печать: ранее внесённая предоплата зачтена, повторно деньги не списываются.'
    : prepaid
      ? 'Печать оплачена предоплатой. После выдачи печати придёт закрывающий чек.'
      : 'Полный расчёт за электронные фотографии.';
}

export const handoverReasonTexts: Record<HandoverBlock, string> = {
  no_payment_receipt: 'Оплачен без чека — закрывающий чек оформляется вручную в кабинете ЮKassa.',
  payment_receipt_failed: 'Чек оплаты не сформирован — закрывающий чек оформляется вручную в кабинете ЮKassa.'
};

const plural = (count: number, forms: readonly [string, string, string]): string => {
  const tens = count % 100;
  const units = count % 10;
  if (tens >= 11 && tens <= 14) return forms[2];
  return units === 1 ? forms[0] : units >= 2 && units <= 4 ? forms[1] : forms[2];
};
const prints = (count: number) => count + ' ' + plural(count, ['отпечаток', 'отпечатка', 'отпечатков']);
// «из N заказов»: after «из» the noun is genitive — «из 1 заказа», «из 3 заказов».
const ofOrders = (count: number) => 'из ' + count + ' ' + plural(count, ['заказа', 'заказов', 'заказов']);

/** «Выдано 1 из 3 заказов» with what still waits; null — the group has no paid print yet. */
export function handoverSummary(view: PrintHandovers): string | null {
  const total = view.orders.length;
  if (!total) return null;
  const { waiting, handed, blocked } = view.summary;
  return (
    'Выдано ' +
    handed +
    ' ' +
    ofOrders(total) +
    ' с печатью' +
    (waiting ? '. Ждут отметки: ' + waiting : '') +
    (blocked ? '. Без закрывающего чека: ' + blocked : '') +
    '.'
  );
}

export function handoverOrderText(order: PrintHandovers['orders'][number]): string {
  return order.orderNumber + ' · ' + (order.childCodes.length ? 'дети ' + order.childCodes.join(', ') + ' · ' : '') + prints(order.prints);
}

/** The server refusal of a handover in words of the organizer; a network failure keeps the selection for a repeat. */
export function handoverErrorText(problem: Pick<ApiProblem, 'network' | 'status' | 'code'>): string {
  if (problem.network) return 'Нет связи с сервером. Проверьте интернет и повторите — повтор ничего не задвоит.';
  switch (problem.code) {
    case 'RECEIPTS_DISABLED':
      return 'Чеки магазина выключены — отметить выдачу нельзя.';
    case 'HANDOVER_CONFLICT':
      return 'Список заказов изменился. Обновите блок и отметьте выдачу снова.';
    case 'INVALID_ORDERS':
      return 'Выберите хотя бы один заказ.';
    case 'GROUP_NOT_FOUND':
      return 'Группа недоступна.';
    case 'ACCESS_CHANGED':
      return 'Права доступа изменились. Обновите страницу.';
    case 'FORBIDDEN':
      return 'Отмечать выдачу может организатор или куратор учреждения.';
    default:
      return 'Не удалось отметить выдачу. Повторите позже.';
  }
}
