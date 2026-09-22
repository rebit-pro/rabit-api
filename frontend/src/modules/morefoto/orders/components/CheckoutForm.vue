<script setup lang="ts">
import type { GallerySnapshot } from '../../gallery/types';
import { money } from '../../commerce/money';
import CartSummary from '../../commerce/components/CartSummary.vue';
import { useCheckout } from '../composables/useCheckout';
import OrderComposition from './OrderComposition.vue';
import CheckoutTerms from './CheckoutTerms.vue';
import CheckoutContacts from './CheckoutContacts.vue';
const props = defineProps<{ gallery: GallerySnapshot; token: string }>();
const { quote, catalog, draft, busy, error, errors, oldTotal, capabilities, previous, canSubmit, recovering, submit } = useCheckout(
  props.gallery,
  props.token
);
</script>
<template>
  <section v-if="recovering" class="mf-panel mf-empty" data-testid="checkout-recovery">
    <v-icon icon="mdi-cloud-sync-outline" color="primary" size="42" />
    <h2 class="my-4">Проверим прошлую отправку</h2>
    <p class="mf-muted mb-5">
      Подтверждение предыдущей отправки заказа не получено. Повторите её: если заказ уже создан, откроется он же, второй заказ не появится.
    </p>
    <v-alert v-if="error" id="checkout-error" type="warning" variant="tonal" class="mb-5" role="alert" tabindex="-1">{{ error }}</v-alert>
    <v-btn color="primary" :loading="busy" :disabled="busy" data-testid="recover-order" @click="submit">Повторить отправку</v-btn>
  </section>
  <section v-else-if="!quote.lines.length && previous" class="mf-panel mf-empty">
    <v-icon icon="mdi-check-circle-outline" color="primary" size="42" />
    <h2 class="my-4">Заказ уже создан</h2>
    <p class="mf-muted mb-5">Повторно оформлять этот выбор не нужно.</p>
    <v-btn :to="'/orders/access/' + previous.accessKey" color="primary">Открыть свой заказ</v-btn>
  </section>
  <section v-else-if="!quote.lines.length && !quote.invalid.length" class="mf-panel mf-empty">
    <h2 class="mb-4">Сначала выберите фотографии</h2>
    <p class="mf-muted mb-5">В этой группе пока нет продукции для оформления.</p>
    <v-btn :to="'/g/' + token" color="primary">Перейти к фотографиям</v-btn>
  </section>
  <form v-else novalidate @submit.prevent="submit">
    <v-alert v-if="gallery.state !== 'open'" type="info" variant="tonal" class="mb-5"
      >Приём заказов закрыт. Контакты и состав можно посмотреть, создать новый заказ сейчас нельзя.</v-alert
    >
    <v-alert v-if="error" id="checkout-error" type="warning" variant="tonal" class="mb-5" role="alert" tabindex="-1">
      {{ error }}
      <p v-if="oldTotal !== null" class="mt-2">Предыдущий итог: {{ money(oldTotal) }}. Новый итог: {{ money(quote.total) }}.</p>
    </v-alert>
    <div class="checkout-layout">
      <div class="checkout-sections">
        <CheckoutContacts
          :draft="draft"
          :errors="errors"
          :busy="busy"
          :max-available="capabilities.maxAvailable"
          :receipt-available="capabilities.receiptAvailable"
          @change="Object.assign(draft, $event)"
        />
        <OrderComposition :quote="quote" />
        <RouterLink :to="'/g/' + token + '/cart'" class="mf-back">Изменить выбор в корзине</RouterLink>
        <CheckoutTerms :gallery="gallery" />
      </div>
      <CartSummary :quote="quote" :catalog="catalog" :staff="gallery.audience === 'staff'">
        <v-checkbox
          v-model="draft.reviewed"
          name="buyer-reviewed"
          label="Состав и демонстрационные условия проверены"
          color="primary"
          :disabled="busy"
          :error-messages="errors.reviewed"
          class="checkout-review"
        />
        <v-btn type="submit" color="primary" block :loading="busy" :disabled="busy || !canSubmit" data-testid="create-order"
          >Создать тестовый заказ</v-btn
        >
        <p class="mf-muted checkout-submit-note">Деньги не списываются. Для заказа создаётся отдельная личная ссылка.</p>
      </CartSummary>
    </div>
  </form>
</template>
<style scoped>
.checkout-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: var(--mf-space-6);
  align-items: start;
}
.checkout-sections {
  display: grid;
  gap: 24px;
}
.checkout-submit-note {
  font-size: 12px;
  line-height: 1.6;
}
.checkout-submit-note {
  margin-top: 14px;
}
.checkout-review {
  margin-top: 24px;
}
@media (max-width: 1000px) {
  .checkout-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
