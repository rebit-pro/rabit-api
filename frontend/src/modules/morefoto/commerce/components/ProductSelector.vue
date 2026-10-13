<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import { computed, shallowRef, useTemplateRef, watch } from 'vue';
import type { GalleryPhoto, GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
import { addToCart } from '../services/cart';
import { money } from '../money';
import { printIncludesCopy } from '../print-copy';
import { sellsKind } from '../sales-windows';
import UiQuantityField from '../../ui/components/UiQuantityField.vue';
import { quantityValue } from '../../ui/field-values';
import { plural } from '@/components/viz/measures';
const props = defineProps<{
  gallery: GallerySnapshot;
  photo: GalleryPhoto;
  token: string;
}>();
const { catalog, quote } = useCart(() => props.gallery);
const productId = shallowRef('print-10x15');
const quantity = shallowRef<number | string>(1);
const quantityField = useTemplateRef<{ validate: () => boolean }>('quantityField');
const busy = shallowRef(false);
const notice = shallowRef('');
const error = shallowRef('');
// E7: once print sales close only digital products and bundles are offered.
const products = computed(() => catalog.value.products.filter((product) => product.active && sellsKind(props.gallery.state, product.kind)));
watch(
  products,
  (items) => {
    if (!items.some((item) => item.id === productId.value)) productId.value = items[0]?.id ?? '';
  },
  { immediate: true }
);
const product = computed(() => products.value.find((product) => product.id === productId.value));
const kindLabels = { physical: 'Печать', digital: 'Электронный файл', bundle: 'Все кадры серии файлами' } as const;
function optionNote(item: { kind: keyof typeof kindLabels; format?: string }): string {
  return item.format ? kindLabels[item.kind] + ' · ' + item.format : kindLabels[item.kind];
}
const discounted = computed(() => isMockApiEnabled && props.gallery.audience === 'staff' && product.value?.staffDiscount);
const childCode = computed(
  () =>
    props.gallery.children.find((child) =>
      child.photos.some((photo) => (photo.assignmentId ?? photo.id) === (props.photo.assignmentId ?? props.photo.id))
    )?.code
);
const covered = computed(
  () =>
    product.value?.kind !== 'physical' &&
    !!childCode.value &&
    ((product.value?.kind === 'bundle' && quote.value.gifts.includes(childCode.value)) ||
      (product.value?.kind === 'digital' &&
        quote.value.lines.some((line) => line.childCode === childCode.value && line.product.kind === 'bundle')))
);
// J4: a print of this frame in the cart already brings its digital copy; the digital product keeps its price.
const printed = computed(() => product.value?.kind === 'digital' && !covered.value && printIncludesCopy(quote.value, props.photo.id));
const unitPrice = computed(() => {
  const price = product.value?.price ?? 0;
  return discounted.value ? Math.ceil(price / 2) : price;
});
const total = computed(() => {
  const count = product.value?.kind === 'physical' ? quantityValue(quantity.value) : 1;
  return count === null ? null : (covered.value ? 0 : unitPrice.value) * count;
});
watch(
  () => props.photo.assignmentId ?? props.photo.id,
  () => {
    quantity.value = 1;
    notice.value = '';
    error.value = '';
  }
);
watch(productId, () => {
  quantity.value = 1;
  notice.value = '';
  error.value = '';
});
async function add() {
  if (busy.value || !product.value) return;
  if (product.value.kind === 'physical' && !quantityField.value?.validate()) return;
  busy.value = true;
  error.value = '';
  notice.value = '';
  try {
    notice.value = await addToCart(
      props.token,
      props.photo.assignmentId ?? props.photo.id,
      productId.value,
      product.value.kind === 'physical' ? Number(quantity.value) : 1
    );
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось сохранить выбор.';
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <!-- UI-DEC-06: products are a short list with prices; one tap picks a product, one more adds it to the cart. -->
  <form class="product-selector" novalidate @submit.prevent="add">
    <h3>Заказать этот кадр</h3>
    <p v-if="isMockApiEnabled" class="product-demo">Демонстрационные цены</p>
    <p v-if="gallery.state === 'digital'" class="mf-muted" data-testid="selector-digital-only">
      Печать закрыта — доступны электронные фотографии.
    </p>
    <fieldset v-if="products.length" class="product-options" :disabled="busy">
      <legend class="mf-sr-only">Продукция</legend>
      <label
        v-for="item in products"
        :key="item.id"
        class="product-option"
        :class="{ 'product-option--active': item.id === productId }"
        data-testid="product-option"
      >
        <input
          v-model="productId"
          type="radio"
          name="product"
          :value="item.id"
          :aria-label="item.name + ', ' + money(item.price)"
          class="product-option__input"
        />
        <span class="product-option__copy">
          <strong>{{ item.name }}</strong>
          <small v-if="item.id === productId"
            >{{ item.description }}<template v-if="item.unit"> Единица: {{ item.unit }}.</template></small
          >
          <small v-else-if="item.kind !== 'physical'">{{ optionNote(item) }}</small>
        </span>
        <span class="product-option__price">{{ money(item.price) }}</span>
      </label>
    </fieldset>
    <p v-else role="status">Ассортимент этой группы пока недоступен.</p>
    <template v-if="product">
      <p v-if="covered" class="product-discount">Входит в электронный комплект вашей корзины без дополнительной оплаты.</p>
      <p v-if="printed" class="product-discount" data-testid="product-print-copy">
        Электронная копия этого кадра входит в печать вашей корзины.
      </p>
      <p v-if="discounted" class="product-discount">
        Скидка сотрудника 50% <s>{{ money(product.price) }}</s>
      </p>
      <div class="product-buy">
        <UiQuantityField
          v-if="product.kind === 'physical'"
          ref="quantityField"
          v-model="quantity"
          :label="product.printCount > 1 ? 'Количество комплектов' : 'Количество'"
          :context="photo.code"
          :disabled="busy"
          density="compact"
          class="product-quantity"
        />
        <div class="product-price">
          <span>{{ isMockApiEnabled ? 'Итого' : 'Цена за единицу' }}</span>
          <strong>{{ isMockApiEnabled ? (total === null ? '—' : money(total)) : money(product.price) }}</strong>
        </div>
      </div>
      <p v-if="product.printCount > 1" class="mf-muted">
        Отпечатков:
        {{ Number(quantity) > 0 ? Number(quantity) * product.printCount : 0 }}
      </p>
      <v-btn
        type="submit"
        color="primary"
        block
        size="large"
        :loading="busy"
        :disabled="busy"
        prepend-icon="mdi-cart-plus"
        data-testid="add-to-cart"
        >Добавить в корзину</v-btn
      >
    </template>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
    <p v-if="notice" role="status" class="product-notice">{{ notice }}</p>
    <v-btn v-if="quote.count" :to="'/g/' + token + '/cart'" variant="tonal" color="primary" block append-icon="mdi-arrow-right"
      >Оформить заказ · {{ quote.count }} {{ plural(quote.count, ['позиция', 'позиции', 'позиций']) }}</v-btn
    >
  </form>
</template>
<style scoped>
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
.product-demo {
  font-size: 12px;
  color: var(--mf-color-text-secondary);
  margin-top: -8px;
}
.product-options {
  display: grid;
  gap: 6px;
  min-width: 0;
  border: 0;
}
.product-option {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--mf-space-3);
  min-height: var(--mf-touch-size);
  padding: 6px var(--mf-space-3);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  cursor: pointer;
  transition:
    border-color var(--mf-duration-fast) var(--mf-ease-standard),
    background-color var(--mf-duration-fast) var(--mf-ease-standard);
}
.product-option:hover {
  border-color: var(--mf-color-border-hover);
}
.product-option--active {
  border-color: var(--mf-color-primary);
  background: var(--mf-color-selected);
}
.product-option:has(.product-option__input:focus-visible) {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
.product-option__input {
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--mf-color-primary);
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
  white-space: nowrap;
}
.product-discount {
  color: var(--mf-color-link);
  font-size: 14px;
}
.product-discount s {
  margin-left: 8px;
  color: var(--mf-color-text-secondary);
}
.product-buy {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.product-quantity {
  width: 160px;
}
.product-price {
  display: grid;
  justify-items: end;
  margin-left: auto;
  font-size: var(--mf-text-xs);
  color: var(--mf-color-text-secondary);
}
.product-price strong {
  color: var(--mf-color-text);
  font-size: 22px;
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
