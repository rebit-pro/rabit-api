<script setup lang="ts">
import PhotoImage from '../../photos/components/PhotoImage.vue';
import type { CartQuoteLine } from '../types';
import { money } from '../money';
import UiQuantityField from '../../ui/components/UiQuantityField.vue';
const props = defineProps<{ line: CartQuoteLine; busy: boolean; closed: boolean }>();
const emit = defineEmits<{ change: [id: string, quantity: number] }>();
function change(quantity: number) {
  if (quantity !== props.line.quantity) emit('change', props.line.id, quantity);
}
</script>
<template>
  <article class="cart-line" data-testid="cart-line">
    <PhotoImage v-if="line.photo" :src="line.photo.thumbSrc" :alt="'Кадр ' + line.photo.code" width="72" height="108" />
    <div v-else class="cart-bundle-icon"><v-icon icon="mdi-image-multiple-outline" size="32" /></div>
    <div class="cart-line__copy">
      <strong>{{ line.product.name }}</strong>
      <p class="mf-muted">{{ line.photo?.code ?? 'Вся серия ' + line.childCode }}</p>
      <p v-if="line.product.format || line.product.unit" class="mf-muted">
        {{ line.product.format }}<template v-if="line.product.unit"> · Единица: {{ line.product.unit }}</template>
      </p>
      <p v-if="line.product.printCount > 1" class="mf-muted">
        В единице: {{ line.product.printCount }} отпечатка · Всего: {{ line.quantity * line.product.printCount }}
      </p>
      <p v-if="line.coveredByGift" class="cart-benefit">Входит в подарочный комплект</p>
      <p v-else-if="line.discount" class="cart-benefit">Льгота 50% · {{ money(line.unitPrice) }} за единицу</p>
    </div>
    <div class="cart-line__actions">
      <UiQuantityField
        v-if="line.product.kind === 'physical'"
        :model-value="line.quantity"
        :context="line.photo?.code ?? line.childCode"
        :disabled="busy || closed"
        @commit="change"
      />
      <span v-else class="mf-muted cart-line__unit">1 комплект / файл</span>
      <strong class="cart-line__total">{{ money(line.total) }}</strong>
      <v-btn
        variant="text"
        color="secondary"
        :aria-label="'Удалить ' + line.id"
        :disabled="busy || closed"
        @click="$emit('change', line.id, 0)"
        >Удалить</v-btn
      >
    </div>
  </article>
</template>
<style scoped>
.cart-line {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  column-gap: var(--mf-space-4);
  row-gap: var(--mf-space-3);
  padding-block: var(--mf-space-6);
  border-bottom: 1px solid var(--mf-color-border);
}
.cart-line:last-of-type {
  border-bottom: 0;
  padding-bottom: 0;
}
.cart-line img {
  object-fit: contain;
  border-radius: var(--mf-radius-field);
  grid-row: span 2;
}
.cart-line__copy {
  min-width: 0;
  font-size: var(--mf-text-small);
  line-height: 1.6;
}
.cart-line__copy > strong {
  font-size: var(--mf-text-control);
}
.cart-line__actions {
  grid-column: 2;
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-3);
  align-items: end;
  min-width: 0;
}
.cart-line__total,
.cart-line__unit {
  min-height: var(--mf-control-height);
  display: flex;
  align-items: center;
}
.cart-line__total {
  white-space: nowrap;
  font-size: 1.125rem;
}
.cart-bundle-icon {
  width: 72px;
  height: 108px;
  grid-row: span 2;
  display: grid;
  place-items: center;
  background: var(--mf-color-selected);
  border-radius: var(--mf-radius-field);
  color: var(--mf-color-link);
}
.cart-benefit {
  color: var(--mf-color-link);
  margin-top: var(--mf-space-1);
}
@media (max-width: 767px) {
  .cart-line__actions {
    grid-column: 1 / -1;
  }
  .cart-line img,
  .cart-bundle-icon {
    grid-row: auto;
  }
}
</style>
