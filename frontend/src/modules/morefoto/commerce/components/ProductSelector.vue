<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import { computed, shallowRef, useTemplateRef, watch } from 'vue';
import type { GalleryPhoto, GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
import { addToCart } from '../services/cart';
import { money } from '../money';
import UiQuantityField from '../../ui/components/UiQuantityField.vue';
import { quantityValue } from '../../ui/field-values';
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
const products = computed(() => catalog.value.products.filter((product) => product.active));
watch(
  products,
  (items) => {
    if (!items.some((item) => item.id === productId.value)) productId.value = items[0]?.id ?? '';
  },
  { immediate: true }
);
const product = computed(() => products.value.find((product) => product.id === productId.value));
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
  <form class="product-selector" novalidate @submit.prevent="add">
    <h3>Заказать этот кадр</h3>
    <p v-if="isMockApiEnabled" class="product-demo">Демонстрационные цены</p>
    <v-select
      v-model="productId"
      :items="products"
      item-title="name"
      item-value="id"
      label="Продукция"
      open-text="Продукция"
      close-text="Продукция"
      :disabled="busy"
      hide-details
    />
    <p v-if="!products.length" role="status">Ассортимент этой группы пока недоступен.</p>
    <template v-if="product">
      <p class="product-description">{{ product.description }}</p>
      <p v-if="product.format || product.unit" class="mf-muted">
        {{ product.format }}<template v-if="product.unit"> · Единица: {{ product.unit }}</template>
      </p>
      <p v-if="covered" class="product-discount">Входит в электронный комплект вашей корзины без дополнительной оплаты.</p>
      <p v-if="discounted" class="product-discount">
        Скидка сотрудника 50% <s>{{ money(product.price) }}</s>
      </p>
      <UiQuantityField
        v-if="product.kind === 'physical'"
        ref="quantityField"
        v-model="quantity"
        :label="product.printCount > 1 ? 'Количество комплектов' : 'Количество'"
        :context="photo.code"
        :disabled="busy"
        class="product-quantity"
      />
      <p v-if="product.printCount > 1" class="mf-muted">
        Отпечатков:
        {{ Number(quantity) > 0 ? Number(quantity) * product.printCount : 0 }}
      </p>
      <div class="product-price">
        <span>{{ isMockApiEnabled ? 'Итого' : 'Цена за единицу до расчёта' }}</span
        ><strong>{{ isMockApiEnabled ? (total === null ? '—' : money(total)) : money(product.price) }}</strong>
      </div>
      <v-btn type="submit" color="primary" block :loading="busy" :disabled="busy" data-testid="add-to-cart">Добавить в корзину</v-btn>
      <v-btn :to="'/g/' + token + '/cart'" variant="text" color="primary" block>Посмотреть корзину</v-btn>
    </template>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
    <p v-if="notice" role="status" class="product-notice">{{ notice }}</p>
  </form>
</template>
<style scoped>
.product-selector {
  padding: var(--mf-space-6);
  align-self: start;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--mf-space-4);
  background: var(--mf-color-surface);
}
.product-selector h3 {
  font-size: 20px;
  line-height: 1.4;
}
.product-demo {
  font-size: 12px;
  color: var(--mf-color-text-secondary);
  margin-top: -10px;
}
.product-description {
  font-size: 14px;
  line-height: 1.6;
  color: var(--mf-color-text-secondary);
}
.product-discount {
  color: var(--mf-color-link);
  font-size: 14px;
}
.product-discount s {
  margin-left: 8px;
  color: var(--mf-color-text-secondary);
}
.product-price {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 8px 0;
}
.product-price strong {
  font-size: 24px;
}
.product-notice {
  padding: 12px;
  background: var(--mf-color-selected);
  border-radius: 8px;
  color: var(--mf-color-link);
  font-size: 14px;
  line-height: 1.5;
}
</style>
