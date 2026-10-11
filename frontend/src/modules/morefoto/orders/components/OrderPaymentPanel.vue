<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useRouter } from 'vue-router';
import { addToStoredCart, rememberedGallery } from '../../commerce/services/storefront';
import { money } from '../../commerce/money';
import { formatMoment } from '../formatters';
import { useLivePayment } from '../composables/useLivePayment';
import { paymentMethodLabels } from '../live/payment-rules';
import { orderSalesBlock, reorderLines } from '../live/rules';
import { documentPath } from '../../legal/rules';
import type { BuyerOrder } from '../live/types';
const props = defineProps<{ order: BuyerOrder; orderKey: string }>();
const paid = computed(() => props.order.paymentStatus === 'paid');
const { quote, loading, starting, uncertain, error, load, pay, follow } = useLivePayment(
  toRef(props, 'orderKey'),
  computed(() => !paid.value)
);
const closed = computed(() => props.order.period.state === 'closed');
// E7: an unpaid order with print cannot be paid after the print close; its digital photos may form a new order.
const block = computed(() => orderSalesBlock(props.order));
const galleryToken = computed(() => rememberedGallery(props.order.groupId));
const router = useRouter();
async function reorder(): Promise<void> {
  if (!block.value || !galleryToken.value) return;
  addToStoredCart(props.order.groupId, reorderLines(block.value.digitalLines));
  await router.push('/g/' + galleryToken.value + '/cart');
}
</script>
<template>
  <section class="order-payment" data-testid="order-payment" aria-live="polite">
    <template v-if="paid">
      <p class="order-payment__done" data-testid="order-paid">
        <v-icon icon="mdi-check-circle" color="success" size="20" /> Оплачено{{
          order.paidAt ? ' ' + formatMoment(order.paidAt) + ' мск' : ''
        }}
      </p>
      <p v-if="order.latePayment" class="order-payment__note" data-testid="order-late-payment">
        Оплата поступила после окончания приёма заказов. Организатор свяжется с вами и сообщит, как будет выполнен заказ.
      </p>
    </template>
    <div v-else-if="block" class="order-payment__blocked" data-testid="order-sales-closed">
      <template v-if="block.kind === 'print'">
        <p class="order-payment__note">
          Приём заказов на печать завершён<template v-if="order.period.closesAt"> {{ formatMoment(order.period.closesAt) }} мск</template>.
          Этот заказ оплатить уже нельзя: в нём есть печатная продукция.
        </p>
        <ul class="order-payment__lines" data-testid="order-print-lines">
          <li v-for="line in block.printLines" :key="line.id">
            {{ line.photo ? 'Кадр ' + line.photo.code : 'Ребёнок ' + line.childCode }} — {{ line.product.name }} × {{ line.quantity }}
          </li>
        </ul>
        <template v-if="block.digitalLines.length">
          <p class="order-payment__note">
            Электронные фотографии из заказа можно купить новым заказом<template v-if="order.period.digitalSalesUntil">
              до {{ formatMoment(order.period.digitalSalesUntil) }} мск</template
            >. Цены пересчитаются, и вы подтвердите новый заказ.
          </p>
          <v-btn v-if="galleryToken" block color="primary" data-testid="order-reorder-digital" @click="reorder"
            >Оформить электронные фото новым заказом</v-btn
          >
          <p v-else class="order-payment__note" data-testid="order-reorder-hint">
            Откройте ссылку на галерею, которую прислал куратор, и выберите эти фотографии снова.
          </p>
        </template>
      </template>
      <p v-else class="order-payment__note">Приём заказов группы завершён — оплатить заказ уже нельзя.</p>
      <v-btn
        v-if="quote?.activeAttemptId"
        variant="text"
        color="primary"
        data-testid="payment-follow"
        @click="follow(quote.activeAttemptId)"
        >Проверить начатую оплату</v-btn
      >
    </div>
    <v-skeleton-loader v-else-if="loading && !quote" type="text, button" />
    <template v-else-if="quote?.activeAttemptId">
      <p class="order-payment__note">Оплата уже начата. Проверьте её результат или продолжите оплату — второй платёж не создаётся.</p>
      <v-btn block color="primary" data-testid="payment-follow" @click="follow(quote.activeAttemptId)"
        >Проверить или продолжить оплату</v-btn
      >
    </template>
    <template v-else-if="quote?.canPay">
      <p class="order-payment__note">
        К оплате <strong data-testid="payment-total">{{ money(quote.quote.total) }}</strong
        >. Вы перейдёте на защищённую страницу ЮKassa, а после оплаты вернётесь сюда. Оплачивая заказ, вы подтверждаете его на условиях
        <RouterLink :to="documentPath('offer')" target="_blank" data-testid="payment-offer">публичной оферты</RouterLink>.
      </p>
      <div class="order-payment__methods">
        <v-btn
          v-for="method in quote.paymentMethods"
          :key="method"
          block
          color="primary"
          :variant="method === quote.paymentMethods[0] ? 'flat' : 'outlined'"
          :loading="starting === method"
          :disabled="starting !== null || (uncertain !== null && uncertain.method !== method)"
          :data-testid="'pay-' + method"
          @click="pay(method)"
          >Оплатить: {{ paymentMethodLabels[method] }}</v-btn
        >
      </div>
    </template>
    <p v-else-if="quote && quote.quote.total === 0" class="order-payment__note" data-testid="payment-not-required">
      Сумма заказа 0 ₽ — оплата не требуется.
    </p>
    <p v-else-if="quote" class="order-payment__note" data-testid="payment-unavailable">
      {{
        closed
          ? 'Приём заказов группы завершён — оплатить заказ уже нельзя.'
          : 'Оплата сейчас недоступна. Заказ сохранён — вернитесь к нему позже.'
      }}
    </p>
    <v-alert v-if="error" type="warning" variant="tonal" density="compact" class="mt-3" role="alert" data-testid="payment-error"
      >{{ error }}<v-btn v-if="!quote" variant="text" size="small" @click="load">Повторить</v-btn></v-alert
    >
  </section>
</template>
<style scoped>
.order-payment {
  display: grid;
  gap: 12px;
  margin-bottom: 20px;
}
.order-payment__blocked {
  display: grid;
  gap: 10px;
}
.order-payment__lines {
  margin: 0;
  padding-left: 20px;
  font-size: 14px;
  line-height: 1.6;
}
.order-payment__methods {
  display: grid;
  gap: 8px;
}
.order-payment__done {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.order-payment__note {
  font-size: 14px;
  line-height: 1.6;
  color: var(--mf-color-text-secondary);
}
</style>
