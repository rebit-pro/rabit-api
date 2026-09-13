import { calculateQuote } from './pricing';
import { resolveDemoGallery } from '../../gallery/services/gallery';
import type { GallerySnapshot } from '../../gallery/types';
import { simulateRequest } from '../../mocks/runtime';
import { readDemo, writeDemo } from '../../mocks/storage';
import { getCatalog } from '../mocks/catalog';
import type { CartLine, CartQuote } from '../types';

export function readCart(groupId: string): CartLine[] {
  const value = readDemo<unknown>('cart:' + groupId, []);
  if (!Array.isArray(value)) return [];
  return value.filter(
    (line): line is CartLine =>
      !!line &&
      typeof line.id === 'string' &&
      typeof line.productId === 'string' &&
      typeof line.childCode === 'string' &&
      (line.photoId === null || typeof line.photoId === 'string') &&
      Number.isInteger(line.quantity) &&
      line.quantity >= 1 &&
      line.quantity <= 99
  );
}
export function quoteCart(gallery: GallerySnapshot, source = readCart(gallery.groupId)): CartQuote {
  return calculateQuote(gallery, source, getCatalog(gallery.groupId));
}

function assertOpen(token: string): GallerySnapshot {
  const gallery = resolveDemoGallery(token);
  if (gallery.state !== 'open' || !gallery.closesAt || Date.parse(gallery.closesAt) <= Date.parse(gallery.referenceNow)) {
    throw new Error('Приём заказов закрыт. Сохранённую корзину можно посмотреть.');
  }
  return gallery;
}
function persist(gallery: GallerySnapshot, lines: CartLine[], notice: string): string {
  const before = quoteCart(gallery).gifts;
  const after = quoteCart(gallery, lines).gifts;
  writeDemo('cart:' + gallery.groupId, lines);
  const lost = before.filter((code) => !after.includes(code));
  return lost.length ? notice + ' Подарочный комплект для ' + lost.join(', ') + ' убран: сумма печатных товаров ниже порога.' : notice;
}
export async function addToCart(token: string, photoId: string, productId: string, quantity: number): Promise<string> {
  await simulateRequest();
  const gallery = assertOpen(token);
  const product = getCatalog(gallery.groupId).products.find((product) => product.id === productId && product.active);
  const child = gallery.children.find((child) => child.photos.some((photo) => photo.id === photoId));
  if (!product || !child) throw new Error('Кадр или продукция больше недоступны. Обновите галерею.');
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) throw new Error('Введите целое количество от 1 до 99.');
  let lines = readCart(gallery.groupId);
  if (product.kind === 'digital' && lines.some((line) => line.childCode === child.code && line.productId === 'bundle'))
    return 'Этот кадр уже входит в полный электронный комплект.';
  const targetPhoto = product.kind === 'bundle' ? null : photoId;
  const id = child.code + ':' + (targetPhoto ?? 'all') + ':' + productId;
  const existing = lines.find((line) => line.id === id);
  if (product.kind !== 'physical' && existing) return 'Электронный файл или комплект уже в корзине.';
  let replaced = false;
  if (product.kind === 'bundle') {
    replaced = lines.some((line) => line.childCode === child.code && line.productId === 'digital');
    lines = lines.filter((line) => !(line.childCode === child.code && line.productId === 'digital'));
  }
  const nextQuantity = product.kind === 'physical' ? (existing?.quantity ?? 0) + quantity : 1;
  if (nextQuantity > 99) throw new Error('В одной позиции можно заказать не более 99 единиц.');
  const next = { id, childCode: child.code, photoId: targetPhoto, productId, quantity: nextQuantity };
  lines = existing ? lines.map((line) => (line.id === id ? next : line)) : [...lines, next];
  return persist(
    gallery,
    lines,
    replaced ? 'Комплект добавлен. Отдельные электронные кадры заменены комплектом без двойной оплаты.' : 'Добавлено в корзину.'
  );
}
export async function changeCartLine(token: string, id: string, quantity: number): Promise<string> {
  await simulateRequest();
  const gallery = assertOpen(token);
  if (!Number.isInteger(quantity) || quantity < 0 || quantity > 99) throw new Error('Количество должно быть от 1 до 99.');
  const lines = readCart(gallery.groupId);
  const line = lines.find((line) => line.id === id);
  if (!line) throw new Error('Позиция уже удалена. Обновите корзину.');
  const product = getCatalog(gallery.groupId).products.find((product) => product.id === line.productId);
  if (quantity > 1 && product?.kind !== 'physical') throw new Error('Для электронного файла достаточно одного экземпляра.');
  return persist(
    gallery,
    quantity === 0 ? lines.filter((line) => line.id !== id) : lines.map((line) => (line.id === id ? { ...line, quantity } : line)),
    quantity ? 'Количество обновлено.' : 'Позиция удалена.'
  );
}
export async function clearCart(token: string): Promise<void> {
  await simulateRequest();
  const gallery = resolveDemoGallery(token);
  writeDemo('cart:' + gallery.groupId, []);
}
