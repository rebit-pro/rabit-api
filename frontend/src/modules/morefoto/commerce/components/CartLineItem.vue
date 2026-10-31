<script setup lang="ts">
import { shallowRef } from 'vue';
import PhotoImage from '../../photos/components/PhotoImage.vue';
import PhotoZoomDialog from '../../gallery/components/PhotoZoomDialog.vue';
import type { CartQuoteLine } from '../types';
import { money } from '../money';
import UiQuantityField from '../../ui/components/UiQuantityField.vue';
const props = defineProps<{
  line: CartQuoteLine;
  busy: boolean;
  closed: boolean;
}>();
const emit = defineEmits<{ change: [id: string, quantity: number] }>();
const open = shallowRef(false);
function change(quantity: number) {
  if (quantity !== props.line.quantity) emit('change', props.line.id, quantity);
}
</script>
<template>
  <!-- UI-DEC-04: one compact row per line — frame, product, quantity, sum and removal on one line on desktop. -->
  <article class="cart-line" data-testid="cart-line">
    <button
      v-if="line.photo"
      type="button"
      class="cart-line__thumb cart-line__open"
      :aria-label="'Увеличить кадр ' + line.photo.code"
      @click="open = true"
    >
      <PhotoImage :src="line.photo.thumbSrc" :alt="'Кадр ' + line.photo.code" />
    </button>
    <div v-else class="cart-line__thumb cart-line__bundle">
      <v-icon icon="mdi-image-multiple-outline" size="24" />
    </div>
    <div class="cart-line__copy">
      <strong>{{ line.product.name }}</strong>
      <span class="mf-muted"
        >{{ line.photo?.code ?? 'Вся серия ' + line.childCode }}<template v-if="line.product.format"> · {{ line.product.format }}</template
        ><template v-if="line.product.printCount > 1"> · {{ line.quantity * line.product.printCount }} отпечатков</template></span
      >
      <span v-if="line.coveredByGift" class="cart-benefit">Входит в подарочный комплект</span>
      <span v-else-if="line.discount" class="cart-benefit">Льгота 50% · {{ money(line.unitPrice) }} за единицу</span>
    </div>
    <UiQuantityField
      v-if="line.product.kind === 'physical'"
      :model-value="line.quantity"
      :context="line.photo?.code ?? line.childCode"
      :disabled="busy || closed"
      density="compact"
      hide-label
      class="cart-line__quantity"
      @commit="change"
    />
    <span v-else class="mf-muted cart-line__unit">Файл</span>
    <strong class="cart-line__total">{{ money(line.total) }}</strong>
    <v-btn
      icon="mdi-close"
      variant="text"
      density="comfortable"
      class="cart-line__remove"
      :aria-label="'Удалить ' + line.product.name + ' ' + (line.photo?.code ?? line.childCode)"
      :disabled="busy || closed"
      @click="$emit('change', line.id, 0)"
    />
    <PhotoZoomDialog :open="open" :src="line.photo?.previewSrc ?? ''" :label="'Кадр ' + (line.photo?.code ?? '')" @close="open = false" />
  </article>
</template>
<style scoped>
.cart-line {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto 96px 44px;
  align-items: center;
  gap: var(--mf-space-3);
  padding-block: var(--mf-space-3);
  border-bottom: 1px solid var(--mf-color-divider);
}
.cart-line:last-of-type {
  border-bottom: 0;
}
.cart-line__thumb {
  width: 48px;
  height: 64px;
  object-fit: cover;
  border-radius: var(--mf-radius-sm);
}
.cart-line__open {
  padding: 0;
  cursor: zoom-in;
}
.cart-line__open img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cart-line__open:focus-visible {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
.cart-line__bundle {
  display: grid;
  place-items: center;
  background: var(--mf-color-selected);
  color: var(--mf-color-link);
}
.cart-line__copy {
  display: grid;
  gap: 2px;
  min-width: 0;
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-snug);
}
.cart-line__copy > strong {
  font-size: var(--mf-text-md);
}
.cart-line__quantity {
  width: 152px;
}
.cart-line__unit {
  font-size: var(--mf-text-sm);
  justify-self: center;
}
.cart-line__total {
  font-variant-numeric: tabular-nums;
  text-align: right;
  white-space: nowrap;
}
.cart-benefit {
  color: var(--mf-color-link);
}
@media (max-width: 767px) {
  .cart-line {
    grid-template-columns: 48px minmax(0, 1fr) 44px;
    grid-template-areas:
      'thumb copy remove'
      'thumb quantity total';
    row-gap: var(--mf-space-2);
  }
  .cart-line__thumb {
    grid-area: thumb;
    align-self: start;
  }
  .cart-line__copy {
    grid-area: copy;
  }
  .cart-line__remove {
    grid-area: remove;
    align-self: start;
  }
  .cart-line__quantity,
  .cart-line__unit {
    grid-area: quantity;
    justify-self: start;
  }
  .cart-line__total {
    grid-area: total;
    justify-self: end;
  }
}
</style>
