<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import { computed, nextTick, shallowRef, useTemplateRef, watch } from 'vue';
import type { GalleryPhoto, GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
import { addToCart, changeCartLine } from '../services/cart';
import { money } from '../money';
import { printIncludesCopy } from '../print-copy';
import { frameLine, frameSummary } from '../frame-lines';
import { sellsKind } from '../sales-windows';
import type { CartQuoteLine, Product } from '../types';
import { plural } from '@/components/viz/measures';
import BuyerGuideLink from '../../guide/BuyerGuideLink.vue';
const props = defineProps<{
  gallery: GallerySnapshot;
  photo: GalleryPhoto;
  token: string;
}>();
const { catalog, quote } = useCart(() => props.gallery);
const list = useTemplateRef<HTMLUListElement>('list');
/** Product whose cart line is being changed; every control waits for the server price of one change at a time. */
const pending = shallowRef('');
const notice = shallowRef('');
const error = shallowRef('');
const assignmentId = computed(() => props.photo.assignmentId ?? props.photo.id);
const childCode = computed(
  () => props.gallery.children.find((child) => child.photos.some((photo) => (photo.assignmentId ?? photo.id) === assignmentId.value))?.code
);
const kindLabels = { physical: 'Печать', digital: 'Электронный файл', bundle: 'Все кадры серии файлами' } as const;
interface Row {
  product: Product;
  line: CartQuoteLine | undefined;
  price: number;
  note: string;
  /** A file or bundle already paid by the series bundle or the gift: nothing to add. */
  covered: boolean;
  /** J4: a print of this frame in the cart brings its digital copy; the digital product keeps its price. */
  printCopy: boolean;
}
// E7: once print sales close only digital products and bundles are offered.
const rows = computed<Row[]>(() =>
  catalog.value.products
    .filter((product) => product.active && sellsKind(props.gallery.state, product.kind))
    .map((product) => {
      const line = frameLine(quote.value, props.photo.id, childCode.value, product);
      const covered =
        !line &&
        !!childCode.value &&
        ((product.kind === 'bundle' && quote.value.gifts.includes(childCode.value)) ||
          (product.kind === 'digital' &&
            quote.value.lines.some((item) => item.childCode === childCode.value && item.product.kind === 'bundle')));
      return {
        product,
        line,
        price:
          isMockApiEnabled && props.gallery.audience === 'staff' && product.staffDiscount ? Math.ceil(product.price / 2) : product.price,
        note:
          product.kind === 'physical'
            ? product.printCount > 1
              ? 'Комплект: ' + product.printCount + ' отпечатка'
              : ''
            : kindLabels[product.kind] + (product.format ? ' · ' + product.format : ''),
        covered,
        printCopy: product.kind === 'digital' && !covered && printIncludesCopy(quote.value, props.photo.id)
      };
    })
);
const summary = computed(() => frameSummary(quote.value, props.photo.id));
watch(assignmentId, () => {
  notice.value = '';
  error.value = '';
});
/** The pressed control is replaced (＋ → counter, counter → ＋), so the focus moves to its successor in the same row. */
async function keepFocus(productId: string): Promise<void> {
  await nextTick();
  const controls = list.value?.querySelector(`[data-product="${CSS.escape(productId)}"] .product-option__control`);
  controls?.querySelector<HTMLElement>('button:last-of-type:not(:disabled)')?.focus();
}
async function run(productId: string, change: () => Promise<string>): Promise<void> {
  if (pending.value) return;
  pending.value = productId;
  error.value = '';
  notice.value = '';
  try {
    notice.value = await change();
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить выбор.';
  } finally {
    pending.value = '';
    void keepFocus(productId);
  }
}
function add(row: Row): void {
  void run(row.product.id, () => addToCart(props.token, assignmentId.value, row.product.id, 1));
}
function change(row: Row, quantity: number): void {
  const line = row.line;
  if (line) void run(row.product.id, () => changeCartLine(props.token, line.id, quantity));
}
</script>
<template>
  <!-- #192: every product has its own «+»; one frame takes several formats and the counters show what is in the cart. -->
  <section class="product-selector" aria-labelledby="product-selector-title">
    <h3 id="product-selector-title">Заказать этот кадр</h3>
    <p class="product-selector__hint">Можно выбрать несколько форматов — нажмите «+» у каждого.</p>
    <p v-if="isMockApiEnabled" class="product-demo">Демонстрационные цены</p>
    <p v-if="gallery.state === 'digital'" class="mf-muted" data-testid="selector-digital-only">
      Печать закрыта — доступны электронные фотографии.
    </p>
    <ul v-if="rows.length" ref="list" class="product-options" :aria-busy="!!pending">
      <li
        v-for="row in rows"
        :key="row.product.id"
        class="product-option"
        :class="{ 'product-option--added': row.line }"
        :data-product="row.product.id"
        data-testid="product-option"
      >
        <span class="product-option__copy">
          <strong>{{ row.product.name }}</strong>
          <span class="product-option__price"
            >{{ money(row.price) }}<s v-if="row.price !== row.product.price">{{ money(row.product.price) }}</s></span
          >
          <small v-if="row.note">{{ row.note }}</small>
        </span>
        <span class="product-option__control">
          <span v-if="row.covered" class="product-option__included" data-testid="product-included">
            <v-icon icon="mdi-check" size="18" aria-hidden="true" />{{ row.product.kind === 'bundle' ? 'В подарок' : 'В комплекте' }}
          </span>
          <template v-else-if="row.line && row.product.kind === 'physical'">
            <v-btn
              icon="mdi-minus"
              variant="outlined"
              color="primary"
              size="small"
              :aria-label="'Убрать один: ' + row.product.name"
              :disabled="!!pending"
              @click="change(row, row.line.quantity - 1)"
            />
            <output class="product-option__count" :aria-label="'В корзине: ' + row.product.name" data-testid="product-count">{{
              row.line.quantity
            }}</output>
            <v-btn
              icon="mdi-plus"
              variant="flat"
              color="primary"
              size="small"
              :aria-label="'Добавить ещё один: ' + row.product.name"
              :loading="pending === row.product.id"
              :disabled="!!pending || row.line.quantity >= 99"
              @click="change(row, row.line.quantity + 1)"
            />
          </template>
          <v-btn
            v-else-if="row.line"
            variant="tonal"
            color="success"
            size="small"
            prepend-icon="mdi-check"
            :aria-label="'В корзине, убрать: ' + row.product.name"
            :loading="pending === row.product.id"
            :disabled="!!pending"
            data-testid="product-remove"
            @click="change(row, 0)"
            >В корзине</v-btn
          >
          <v-btn
            v-else
            icon="mdi-plus"
            variant="flat"
            color="primary"
            size="small"
            :aria-label="'Добавить в корзину: ' + row.product.name + ', ' + money(row.price)"
            :loading="pending === row.product.id"
            :disabled="!!pending"
            data-testid="product-add"
            @click="add(row)"
          />
        </span>
        <p v-if="row.printCopy && !row.line" class="product-option__copy-note" data-testid="product-print-copy">
          Электронная копия этого кадра входит в печать вашей корзины.
        </p>
      </li>
    </ul>
    <p v-else role="status">Ассортимент этой группы пока недоступен.</p>
    <p v-if="summary.count" class="product-summary" data-testid="frame-summary">
      В корзине из этого кадра: {{ summary.count }} шт. · {{ money(summary.total) }}
    </p>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
    <p class="product-notice" :class="{ 'mf-sr-only': !notice }" role="status">{{ notice }}</p>
    <v-btn v-if="quote.count" :to="'/g/' + token + '/cart'" color="primary" block size="large" append-icon="mdi-arrow-right"
      >Оформить заказ · {{ quote.count }} {{ plural(quote.count, ['позиция', 'позиции', 'позиций']) }}</v-btn
    >
    <BuyerGuideLink step="foto" class="product-selector__guide" />
  </section>
</template>
<style scoped>
.product-selector__guide {
  align-self: center;
}
.product-selector {
  padding: var(--mf-space-5);
  align-self: start;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--mf-space-3);
  background: var(--mf-color-surface);
}
.product-selector h3 {
  font-size: 18px;
  line-height: 1.4;
}
.product-selector__hint {
  margin-top: -8px;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  line-height: 1.45;
}
.product-demo {
  font-size: 12px;
  color: var(--mf-color-text-secondary);
}
.product-options {
  display: grid;
  gap: 6px;
  min-width: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}
.product-option {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 4px var(--mf-space-3);
  min-height: 60px;
  padding: 8px 10px 8px var(--mf-space-3);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  transition:
    border-color var(--mf-duration-fast) var(--mf-ease-standard),
    background-color var(--mf-duration-fast) var(--mf-ease-standard);
}
.product-option--added {
  border-color: var(--mf-color-primary);
  background: var(--mf-color-selected);
}
.product-option__copy {
  display: grid;
  min-width: 0;
  line-height: 1.35;
}
.product-option__copy strong {
  font-size: var(--mf-text-md);
  font-weight: 600;
}
.product-option__copy small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-xs);
}
.product-option__price {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: var(--mf-color-text-secondary);
}
.product-option__price s {
  margin-left: 8px;
  font-weight: 400;
}
.product-option__control {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.product-option__count {
  min-width: 24px;
  text-align: center;
  font-size: var(--mf-text-md);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.product-option__included {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--mf-tone-success-fg);
  font-size: var(--mf-text-sm);
  font-weight: 600;
}
.product-option__copy-note {
  grid-column: 1 / -1;
  color: var(--mf-color-link);
  font-size: var(--mf-text-xs);
  line-height: 1.45;
}
.product-summary {
  font-size: var(--mf-text-sm);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.product-notice {
  padding: 10px 12px;
  background: var(--mf-color-selected);
  border-radius: 8px;
  color: var(--mf-color-link);
  font-size: 14px;
  line-height: 1.5;
}
</style>
