<script setup lang="ts">
import { computed } from 'vue';
import { money } from '../../commerce/money';
import { usePaymentReturn } from '../composables/usePaymentReturn';
import { returnState } from '../live/payment-rules';
const { attempt, orderKey, error, waitOver, recheck } = usePaymentReturn();
const orderLink = computed(() => (orderKey.value ? '/orders/access/' + orderKey.value : null));
const state = computed(() => returnState(attempt.value, waitOver.value));
const views = {
  paid: { icon: 'mdi-check-circle', color: 'success', title: 'Заказ оплачен', text: 'Оплата подтверждена ЮKassa. Статус заказа обновлён.' },
  failed: {
    icon: 'mdi-close-circle',
    color: 'error',
    title: 'Оплата не прошла',
    text: 'Деньги не списаны. Вернитесь к заказу, чтобы попробовать ещё раз.'
  },
  continue: {
    icon: 'mdi-timer-sand',
    color: 'primary',
    title: 'Оплата не завершена',
    text: 'Если вы уже оплатили, результат появится здесь сам. Если нет — продолжите оплату на странице ЮKassa: второй платёж не создаётся.'
  },
  checking: {
    icon: 'mdi-timer-sand',
    color: 'primary',
    title: 'Проверяем оплату',
    text: 'Это займёт несколько секунд. Не оплачивайте заказ повторно.'
  },
  waiting: {
    icon: 'mdi-timer-sand',
    color: 'primary',
    title: 'Проверяем оплату',
    text: 'ЮKassa ещё не подтвердила результат. Мы продолжим проверку сами — результат появится на странице заказа.'
  }
} as const;
const view = computed(() => views[state.value]);
</script>
<template>
  <main class="mf-main payment-return">
    <section v-if="!orderKey" class="mf-panel mf-empty" data-testid="payment-return-no-key">
      <h1>Откройте заказ по личной ссылке</h1>
      <p class="mf-muted my-5">
        На этом устройстве нет личной ссылки на заказ. Откройте заказ по ссылке из письма или сообщения — там будет результат оплаты.
      </p>
    </section>
    <section v-else class="mf-panel mf-empty" data-testid="payment-return" :data-status="attempt?.status ?? 'loading'">
      <v-icon :icon="view.icon" :color="view.color" size="56" />
      <h1 data-testid="payment-return-title">{{ view.title }}</h1>
      <p v-if="attempt" class="payment-return__amount">{{ money(attempt.amount) }}</p>
      <p class="mf-muted">{{ view.text }}</p>
      <p v-if="attempt?.latePayment" class="mf-muted">
        Оплата поступила после окончания приёма заказов — организатор сообщит, как будет выполнен заказ.
      </p>
      <v-alert v-if="error" type="warning" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
      <div class="mf-actions payment-return__actions">
        <v-btn
          v-if="state === 'continue' && attempt?.redirectUrl"
          :href="attempt.redirectUrl"
          color="primary"
          data-testid="payment-continue"
          >Продолжить оплату</v-btn
        >
        <v-btn v-if="waitOver" variant="outlined" @click="recheck">Проверить ещё раз</v-btn>
        <v-btn
          v-if="orderLink"
          :to="orderLink"
          :variant="state === 'continue' ? 'outlined' : 'flat'"
          color="primary"
          data-testid="payment-return-order"
          >Вернуться к заказу</v-btn
        >
      </div>
    </section>
  </main>
</template>
<style scoped>
.payment-return {
  width: 100%;
  min-height: 100svh;
}
.payment-return section {
  display: grid;
  justify-items: center;
  gap: 12px;
  max-width: 560px;
  margin: 48px auto;
  text-align: center;
}
.payment-return__amount {
  font-size: 28px;
  font-weight: 600;
}
.payment-return__actions {
  justify-content: center;
  margin-top: 8px;
}
</style>
