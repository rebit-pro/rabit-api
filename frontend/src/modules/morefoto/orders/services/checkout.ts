import { digitalEntitlements } from './entitlements';
import { quoteCart } from '../../commerce/services/cart';
import type { CartQuote } from '../../commerce/types';
import { resolveDemoGallery } from '../../gallery/services/gallery';
import { readDemo, writeDemo } from '../../mocks/storage';
import { simulateRequest } from '../../mocks/runtime';
import { normalizeBuyer, validateBuyer } from './validation';
import { randomKey, readOrders, saveOrder, withOrderLock } from './orders';
import type { BuyerFields, CheckoutDraft, OrderSnapshot } from '../types';
export function checkoutCapabilities(): { maxAvailable: boolean } {
  return readDemo('checkout:capabilities', { maxAvailable: false });
}
export function saveCheckoutDraft(groupId: string, draft: CheckoutDraft): void {
  writeDemo('checkout:' + groupId, draft);
}
export function readCheckoutDraft(groupId: string): CheckoutDraft {
  const stored = readDemo<Partial<CheckoutDraft>>('checkout:' + groupId, {});
  const draft: CheckoutDraft = {
    name: typeof stored.name === 'string' ? stored.name : '',
    phone: typeof stored.phone === 'string' ? stored.phone : '',
    email: typeof stored.email === 'string' ? stored.email : '',
    comment: typeof stored.comment === 'string' ? stored.comment : '',
    receiptChannel: stored.receiptChannel === 'max' ? 'max' : 'email',
    reviewed: false,
    requestId: typeof stored.requestId === 'string' && /^[a-f0-9]{32}$/.test(stored.requestId) ? stored.requestId : randomKey()
  };
  saveCheckoutDraft(groupId, draft);
  return draft;
}
export function quoteSignature(quote: CartQuote): string {
  return JSON.stringify(quote);
}
export class CheckoutChangedError extends Error {
  constructor(public quote: CartQuote) {
    super('Состав или цена изменились. Проверьте обновлённый итог и подтвердите его ещё раз.');
  }
}
export class CheckoutValidationError extends Error {
  constructor(public fields: ReturnType<typeof validateBuyer>) {
    super('Проверьте заполнение формы.');
  }
}
export async function createOrder(token: string, fields: BuyerFields, requestId: string, signature: string): Promise<OrderSnapshot> {
  await simulateRequest();
  const result = await withOrderLock(() => {
    const gallery = resolveDemoGallery(token);
    // A retry after a lost response returns the same order, even after the group closes.
    const previous = readOrders().find((order) => order.requestId === requestId && order.groupId === gallery.groupId);
    if (previous) return previous;
    if (!/^[a-f0-9]{32}$/.test(requestId)) throw new Error('Обновите страницу оформления.');
    if (gallery.state !== 'open' || !gallery.closesAt || Date.parse(gallery.closesAt) <= Date.parse(gallery.referenceNow))
      throw new Error('Приём заказов закрыт. Новый заказ создать нельзя; контакты и выбор сохранены.');
    const errors = validateBuyer(fields, checkoutCapabilities().maxAvailable);
    if (Object.keys(errors).length) throw new CheckoutValidationError(errors);
    const quote = quoteCart(gallery);
    if (quoteSignature(quote) !== signature) throw new CheckoutChangedError(quote);
    if (!quote.lines.length || quote.invalid.length) throw new Error('Проверьте доступные позиции в корзине перед оформлением.');
    const digitalPhotos = digitalEntitlements(gallery, quote);
    const order: OrderSnapshot = {
      id: randomKey(),
      accessKey: randomKey(),
      requestId,
      number: 'MF-' + String(readOrders().length + 1).padStart(6, '0'),
      groupId: gallery.groupId,
      galleryToken: token,
      institutionName: gallery.institutionName,
      groupName: gallery.groupName,
      shootName: gallery.shootName,
      audience: gallery.audience,
      createdAt: gallery.referenceNow,
      closesAt: gallery.closesAt,
      buyer: normalizeBuyer(fields),
      quote,
      digitalPhotos,
      paymentStatus: 'unpaid',
      productionStatus: 'not-started'
    };
    saveOrder(order);
    writeDemo('cart:' + gallery.groupId, []);
    return structuredClone(order);
  });
  if (readDemo('checkout:lose-response-once', false)) {
    writeDemo('checkout:lose-response-once', false);
    throw new Error('Подтверждение оформления не получено. Повторите отправку: уже созданный заказ будет восстановлен.');
  }
  return result;
}
