import { isAxiosError } from 'axios';
import { nextTick, onScopeDispose, ref, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { moneyInputValue, quantityValue } from '../ui/field-values';
import type { ManagementErrors, ProductCommand } from '../management/types';
import { catalogApi, catalogError, type CatalogProduct, type ProductAttempt, type ProductPayload } from './api';

interface Draft {
  command: ProductCommand;
  id: string | null;
  pending: ProductAttempt | null;
}
function newCommand(revision: number, product?: CatalogProduct): ProductCommand {
  return {
    kind: 'product',
    requestId: crypto.randomUUID().replace(/-/g, ''),
    revision,
    product: product
      ? { ...product, price: String(product.price / 100), printCount: String(product.printCount) }
      : {
          id: '',
          name: '',
          description: '',
          kind: 'physical',
          price: '0',
          printCount: '1',
          format: '',
          unit: 'шт.',
          active: true,
          staffDiscount: false
        }
  };
}
function validate(command: ProductCommand): ManagementErrors {
  const p = command.product,
    errors: ManagementErrors = {};
  if (!p.name.trim() || [...p.name].length > 255) errors.name = 'Название: от 1 до 255 символов.';
  if ([...p.description].length > 4000) errors.description = 'Описание: до 4000 символов.';
  if ([...p.format].length > 100) errors.format = 'Формат: до 100 символов.';
  if ([...p.unit].length > 100) errors.unit = 'Единица продажи: до 100 символов.';
  const price = moneyInputValue(p.price);
  if (price === null || price > 2147483647) errors.price = 'Цена: от 0 до 21 474 836,47 ₽, до двух знаков после запятой.';
  if (p.kind === 'physical' && quantityValue(p.printCount, 0, 2147483647) === null)
    errors.printCount = 'Укажите целое неотрицательное число отпечатков.';
  return errors;
}
export function useProductEditor(saved: () => Promise<unknown>) {
  const auth = useAuthStore();
  const command = ref<ProductCommand | null>(null);
  const existingId = shallowRef<string | null>(null);
  const pending = shallowRef<ProductAttempt | null>(null);
  const busy = shallowRef(false),
    error = shallowRef(''),
    errors = shallowRef<ManagementErrors>({}),
    restored = shallowRef(false);
  let key = '',
    alive = true;
  function persist(): void {
    if (command.value && key)
      localStorage.setItem(key, JSON.stringify({ command: command.value, id: existingId.value, pending: pending.value }));
  }
  function open(revision: number, product?: CatalogProduct): void {
    if (busy.value) return;
    key = `morefoto:live:catalog-draft:${auth.user?.id}:${product?.id ?? 'new'}`;
    existingId.value = product?.id ?? null;
    pending.value = null;
    let draft: Draft | null = null;
    try {
      draft = JSON.parse(localStorage.getItem(key) ?? 'null') as Draft | null;
    } catch {
      /* Invalid local draft is ignored. */
    }
    const valid =
      draft?.command?.kind === 'product' &&
      draft.id === existingId.value &&
      draft.command.product &&
      typeof draft.command.requestId === 'string';
    command.value = valid && draft ? draft.command : newCommand(revision, product);
    pending.value = valid && draft ? (draft.pending ?? null) : null;
    restored.value = !!valid;
    error.value = pending.value
      ? 'Ответ на сохранение не получен. Повторите сохранение, чтобы проверить результат без создания дубликата.'
      : '';
    errors.value = {};
    persist();
  }
  function close(): void {
    if (!busy.value) command.value = null;
  }
  async function reset(): Promise<void> {
    if (busy.value || pending.value) return;
    busy.value = true;
    try {
      let result = await catalogApi.list(1, 100);
      let product = result.data.items.find((item) => item.id === existingId.value);
      for (let page = 2; existingId.value && !product && (page - 1) * 100 < result.meta.total; page++) {
        result = await catalogApi.list(page, 100);
        product = result.data.items.find((item) => item.id === existingId.value);
      }
      if (!alive) return;
      if (existingId.value && !product) {
        error.value = 'Товар больше недоступен. Закройте редактор и обновите каталог.';
        return;
      }
      command.value = newCommand(result.data.revision, product);
      restored.value = false;
      error.value = '';
      errors.value = {};
      persist();
    } catch (cause) {
      if (alive) error.value = catalogError(cause);
    } finally {
      if (alive) busy.value = false;
    }
  }
  async function save(): Promise<void> {
    if (!command.value || busy.value) return;
    errors.value = pending.value ? {} : validate(command.value);
    if (Object.keys(errors.value).length) {
      error.value = 'Проверьте выделенные поля.';
      await nextTick();
      document.querySelector<HTMLElement>('[data-testid="admin-dialog"] [aria-invalid="true"]')?.focus();
      return;
    }
    if (!pending.value) {
      const p = command.value.product;
      const body: ProductPayload & { revision?: number } = {
        name: p.name.trim(),
        description: p.description,
        kind: p.kind,
        price: moneyInputValue(p.price)!,
        printCount: p.kind === 'physical' ? Number(p.printCount) : 0,
        format: p.format,
        unit: p.unit,
        staffDiscount: p.staffDiscount,
        active: p.active
      };
      if (existingId.value) body.revision = command.value.revision;
      pending.value = { id: existingId.value, key: command.value.requestId, body };
      persist();
    }
    busy.value = true;
    error.value = '';
    try {
      await catalogApi.save(pending.value);
      localStorage.removeItem(key);
      if (alive) {
        command.value = null;
        pending.value = null;
        await saved();
      }
    } catch (cause) {
      if (!alive) return;
      const status = isAxiosError(cause) ? cause.response?.status : undefined;
      // A transport/5xx failure may have happened after commit. Retry the exact persisted request.
      if (status && status < 500) {
        pending.value = null;
        if (command.value) command.value.requestId = crypto.randomUUID().replace(/-/g, '');
      }
      error.value = pending.value
        ? 'Ответ на сохранение не получен. Повторите сохранение, чтобы проверить результат без создания дубликата.'
        : catalogError(cause);
      persist();
    } finally {
      if (alive) busy.value = false;
    }
  }
  watch(command, persist, { deep: true, flush: 'sync' });
  onScopeDispose(() => {
    alive = false;
  });
  return { command, existingId, pending, busy, error, errors, restored, open, close, reset, save };
}
