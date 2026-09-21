import { shallowReactive } from 'vue';
import api from '@/api/http';
import type { GallerySnapshot } from '../../gallery/types';
import type { CartQuote, Catalog } from '../types';

interface Line {
  assignmentId: string;
  productId: string;
  quantity: number;
}
interface State {
  gallery: GallerySnapshot;
  token: string;
  catalog: Catalog;
  lines: Line[];
  quote: CartQuote | null;
  busy: boolean;
  error: string;
}
const states = shallowReactive(new Map<string, State>());
const empty: CartQuote = {
  lines: [],
  total: 0,
  subtotal: 0,
  discount: 0,
  giftSaving: 0,
  gifts: [],
  count: 0,
  invalid: [],
  revision: 0
};
export function liveState(groupId: string): State {
  const state = states.get(groupId);
  if (!state) throw new Error('Галерея ещё не загружена.');
  return state;
}
export function liveQuote(groupId: string): CartQuote {
  return liveState(groupId).quote ?? empty;
}
function key(groupId: string): string {
  return 'morefoto:cart:v1:' + groupId;
}
function read(groupId: string): Line[] {
  try {
    const value: unknown = JSON.parse(localStorage.getItem(key(groupId)) ?? '[]');
    if (!Array.isArray(value) || value.length > 100) return [];
    return value
      .filter(
        (line): line is Line =>
          !!line &&
          typeof line.assignmentId === 'string' &&
          typeof line.productId === 'string' &&
          Number.isInteger(line.quantity) &&
          line.quantity >= 1 &&
          line.quantity <= 99
      )
      .map(({ assignmentId, productId, quantity }) => ({
        assignmentId,
        productId,
        quantity
      }));
  } catch {
    return [];
  }
}
function message(cause: unknown): string {
  if (cause instanceof Error && 'isAxiosError' in cause) {
    return 'Не удалось проверить корзину. Обновите расчёт или очистите сохранённый выбор.';
  }
  return cause instanceof Error ? cause.message : 'Не удалось рассчитать корзину.';
}
async function calculate(state: State, lines: Line[]): Promise<CartQuote> {
  const { data } = await api.post<{ quote: CartQuote }>('/api/v1/public/galleries/' + state.token + '/quotes', {
    lines: lines.map(({ assignmentId, productId, quantity }) => ({
      assignmentId,
      productId,
      quantity
    }))
  });
  return data.quote;
}
export async function loadStorefront(token: string, gallery: GallerySnapshot): Promise<void> {
  if (gallery.state === 'preparing') {
    states.set(
      gallery.groupId,
      shallowReactive<State>({
        gallery,
        token,
        catalog: {
          products: [],
          giftThreshold: 0,
          giftForStaff: false,
          revision: 0
        },
        lines: read(gallery.groupId),
        quote: null,
        busy: false,
        error: ''
      })
    );
    return;
  }
  let catalog: Catalog;
  try {
    ({ data: catalog } = await api.get<Catalog>('/api/v1/public/galleries/' + token + '/catalog'));
  } catch (cause) {
    states.set(
      gallery.groupId,
      shallowReactive<State>({
        gallery,
        token,
        catalog: { products: [], giftThreshold: 0, giftForStaff: false, revision: 0 },
        lines: read(gallery.groupId),
        quote: null,
        busy: false,
        error: message(cause)
      })
    );
    return;
  }
  const state = shallowReactive<State>({
    gallery,
    token,
    catalog,
    lines: read(gallery.groupId),
    quote: null,
    busy: false,
    error: ''
  });
  states.set(gallery.groupId, state);
  if (gallery.state === 'open') await refreshStorefront(gallery.groupId);
}
export async function refreshStorefront(groupId: string): Promise<void> {
  const state = liveState(groupId);
  if (state.busy) return;
  state.busy = true;
  state.error = '';
  try {
    state.quote = await calculate(state, state.lines);
  } catch (cause) {
    state.quote = null;
    state.error = message(cause);
  } finally {
    state.busy = false;
  }
}
async function persist(state: State, lines: Line[]): Promise<void> {
  if (state.busy) throw new Error('Дождитесь завершения расчёта.');
  state.busy = true;
  state.error = '';
  try {
    const quote = await calculate(state, lines);
    localStorage.setItem(key(state.gallery.groupId), JSON.stringify(lines));
    state.lines = lines;
    state.quote = quote;
  } catch (cause) {
    state.error = message(cause);
    throw new Error(state.error);
  } finally {
    state.busy = false;
  }
}
function forToken(token: string): State {
  const state = [...states.values()].find((item) => item.token === token);
  if (!state) throw new Error('Обновите галерею.');
  return state;
}
export async function addLive(token: string, assignmentId: string, productId: string, quantity: number): Promise<string> {
  const state = forToken(token);
  const selectedChild = state.gallery.children.find((child) => child.photos.some((photo) => photo.assignmentId === assignmentId));
  const product = state.catalog.products.find((item) => item.id === productId);
  if (!selectedChild || !product) throw new Error('Кадр или товар недоступен.');
  const childAssignments = new Set(selectedChild.photos.map((photo) => photo.assignmentId));
  const productKinds = new Map(state.catalog.products.map((item) => [item.id, item.kind]));
  if (
    product.kind === 'digital' &&
    state.lines.some((line) => childAssignments.has(line.assignmentId) && productKinds.get(line.productId) === 'bundle')
  )
    return 'Этот кадр уже входит в электронный комплект корзины.';
  const source =
    product.kind === 'bundle'
      ? state.lines.filter((line) => !(childAssignments.has(line.assignmentId) && productKinds.get(line.productId) === 'digital'))
      : state.lines;
  const existing = source.find(
    (line) =>
      line.productId === productId &&
      (product.kind === 'bundle'
        ? selectedChild.photos.some((photo) => photo.assignmentId === line.assignmentId)
        : line.assignmentId === assignmentId)
  );
  const next = {
    assignmentId: existing?.assignmentId ?? assignmentId,
    productId,
    quantity: product.kind === 'physical' ? (existing?.quantity ?? 0) + quantity : 1
  };
  if (!Number.isInteger(next.quantity) || next.quantity < 1 || next.quantity > 99) throw new Error('Укажите количество от 1 до 99.');
  const lines = existing ? source.map((line) => (line === existing ? next : line)) : [...source, next];
  await persist(state, lines);
  return 'Добавлено в корзину. Итог проверен сервером.';
}
export async function changeLive(token: string, id: string, quantity: number): Promise<string> {
  const state = forToken(token);
  const priced = state.quote?.lines.find((line) => line.id === id);
  if (!priced?.assignmentId) throw new Error('Обновите расчёт корзины.');
  const lines = state.lines.flatMap((line) =>
    line.assignmentId === priced.assignmentId && line.productId === priced.productId
      ? quantity === 0
        ? []
        : [{ ...line, quantity }]
      : [line]
  );
  await persist(state, lines);
  return quantity ? 'Количество обновлено.' : 'Позиция удалена.';
}
export async function clearLive(token: string): Promise<void> {
  const state = forToken(token);
  if (state.busy) throw new Error('Дождитесь завершения расчёта.');
  if (state.gallery.state === 'open') {
    await persist(state, []);
  }
  localStorage.removeItem(key(state.gallery.groupId));
  state.lines = [];
  state.error = '';
  if (state.gallery.state !== 'open') state.quote = null;
}
