<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import { refreshStorefront } from '../services/storefront';
import { computed, shallowRef, useTemplateRef } from 'vue';
import { galleryBuys } from '../sales-windows';
import type { GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
import { changeCartLine, clearCart } from '../services/cart';
import CartItems from './CartItems.vue';
import CartSummary from './CartSummary.vue';
import CheckoutPanel from '../../orders/components/CheckoutPanel.vue';
import CheckoutTerms from '../../orders/components/CheckoutTerms.vue';
import { useLegalCatalog } from '../../legal/useLegalCatalog';
import { findDocument } from '../../legal/rules';
const props = defineProps<{ gallery: GallerySnapshot; token: string }>();
const { quote, catalog, calculationError, hasQuote, droppedPrint } = useCart(() => props.gallery);
const selling = computed(() => galleryBuys(props.gallery));
const purchasable = computed(() => isMockApiEnabled || !!catalog.value.capabilities?.purchaseEnabled);
const { catalog: legal } = useLegalCatalog();
const offer = computed(() => findDocument(legal.value, 'offer'));
const busy = shallowRef(false);
const notice = shallowRef('');
const error = shallowRef('');
const confirmClear = shallowRef(false);
const clearButton = useTemplateRef<{ $el: HTMLButtonElement }>('clearButton');
const emptyButton = useTemplateRef<{ $el: HTMLElement }>('emptyButton');
function openClear() {
  error.value = '';
  confirmClear.value = true;
}
function returnFocus() {
  (clearButton.value?.$el ?? emptyButton.value?.$el)?.focus();
}
async function change(id: string, quantity: number) {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  notice.value = '';
  try {
    notice.value = await changeCartLine(props.token, id, quantity);
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось изменить корзину.';
  } finally {
    busy.value = false;
  }
}
async function clear() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await clearCart(props.token);
    confirmClear.value = false;
    notice.value = 'Корзина этой группы очищена.';
  } catch (cause) {
    error.value = cause instanceof Error ? cause.message : 'Не удалось очистить корзину.';
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <p class="cart-separation">
    <v-icon icon="mdi-information-outline" size="18" /> В каждой группе своя корзина. Выбор в других группах сохраняется отдельно.
  </p>
  <v-alert v-if="gallery.updating" type="info" variant="tonal" class="mb-5" data-testid="cart-updating"
    >Фотографии группы обновляются, покупка временно приостановлена. Выбор сохранён — вернитесь немного позже.</v-alert
  >
  <v-alert v-else-if="!selling" type="info" variant="tonal" class="mb-5"
    >Приём заказов закрыт. Выбор сохранён; новые заказы сейчас не принимаются.</v-alert
  >
  <v-alert v-else-if="gallery.state === 'digital'" type="info" variant="tonal" class="mb-5" data-testid="cart-digital-only"
    >Приём заказов на печать завершён — в заказ войдут только электронные фотографии.<template v-if="droppedPrint">
      Печатные позиции убраны из корзины: {{ droppedPrint }}.</template
    ></v-alert
  >
  <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
  <p v-if="notice" class="cart-notice" role="status">{{ notice }}</p>
  <v-alert v-if="calculationError" type="error" variant="tonal" class="mb-5">
    {{ calculationError }}
    <v-btn variant="text" @click="refreshStorefront(gallery.groupId)">Повторить расчёт</v-btn>
    <v-btn variant="text" @click="openClear">Очистить сохранённый выбор</v-btn>
  </v-alert>
  <v-alert v-if="quote.invalid.length" type="warning" variant="tonal" class="mb-5"
    >Часть выбранных товаров больше недоступна. Очистите корзину и выберите доступные фотографии заново.</v-alert
  >
  <template v-if="hasQuote && (quote.lines.length || quote.invalid.length)">
    <div class="cart-layout">
      <div class="cart-items-column">
        <CartItems :quote="quote" :busy="busy" :closed="!selling" @change="change" />
        <div class="cart-tools">
          <v-btn :to="'/g/' + token" variant="text" color="primary" prepend-icon="mdi-plus">Добавить ещё фото</v-btn>
          <v-btn ref="clearButton" variant="text" color="secondary" :disabled="busy" @click="openClear">Очистить корзину</v-btn>
        </div>
      </div>
      <!-- UI-DEC-04: the order is placed right here; the old checkout address leads to this page. -->
      <CheckoutPanel v-if="purchasable" :key="token" class="cart-layout__order" :gallery="gallery" :token="token" />
      <CartSummary v-else class="cart-layout__order" :quote="quote" :catalog="catalog" :staff="gallery.audience === 'staff'">
        <p class="cart-unavailable" role="status">Оформление заказов в этой группе пока не открыто.</p>
      </CartSummary>
      <!-- On a phone the terms follow the order button; on a wide screen they stay under the cart. -->
      <CheckoutTerms class="cart-layout__terms" :gallery="gallery" :seller="legal?.seller ?? null" :offer="offer" />
    </div>
  </template>
  <section v-else-if="hasQuote" class="mf-panel mf-empty">
    <v-icon icon="mdi-cart-outline" size="42" color="primary" />
    <h2 class="my-4">Корзина пока пуста</h2>
    <p class="mf-muted mb-5">Откройте кадр в галерее и выберите продукцию.</p>
    <v-btn ref="emptyButton" :to="'/g/' + token" color="primary">Перейти к фотографиям</v-btn>
  </section>
  <v-dialog v-model="confirmClear" max-width="460" aria-labelledby="clear-cart-title" :persistent="busy" @after-leave="returnFocus">
    <v-card class="mf-panel morefoto-app">
      <h2 id="clear-cart-title">Очистить корзину этой группы?</h2>
      <p class="mf-muted my-4">
        Будет удалён выбор только для группы «{{ gallery.groupName }}». Другие корзины и оплаченные заказы сохранятся.
      </p>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-4">{{ error }}</v-alert>
      <div class="mf-actions">
        <v-btn color="error" :loading="busy" :disabled="busy" @click="clear">Да, очистить</v-btn>
        <v-btn variant="outlined" :disabled="busy" @click="confirmClear = false">Оставить выбор</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.cart-items-column {
  display: grid;
  gap: var(--mf-space-4);
  align-content: start;
  min-width: 0;
}
.cart-tools {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--mf-space-2);
}
.cart-unavailable {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.cart-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  /* The tall order panel spans both rows; its extra height goes to the second row, under the terms. */
  grid-template-rows: auto 1fr;
  gap: var(--mf-space-4) var(--mf-space-6);
  align-items: start;
}
.cart-layout__order {
  grid-column: 2;
  grid-row: 1 / span 2;
}
.cart-layout__terms {
  grid-column: 1;
}
.cart-separation {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: var(--mf-text-sm);
  line-height: 1.5;
  color: var(--mf-color-text-secondary);
  margin-bottom: var(--mf-space-4);
}
.cart-notice {
  padding: 16px;
  margin-bottom: 20px;
  background: var(--mf-color-selected);
  color: var(--mf-color-link);
  border-radius: 8px;
  line-height: 1.5;
}
@media (max-width: 1000px) {
  .cart-layout {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: none;
  }
  .cart-layout__order,
  .cart-layout__terms {
    grid-column: 1;
    grid-row: auto;
  }
}
</style>
