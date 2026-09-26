<script setup lang="ts">
import { computed } from 'vue';
import { currentBuyer } from '../../settlement/rules';
import { formatPhone } from '../../ui/field-values';
import type { OrderSnapshot } from '../types';
const props = defineProps<{ order: Pick<OrderSnapshot, 'buyer' | 'settlement'> }>();
const buyer = computed(() => currentBuyer(props.order));
</script>
<template>
  <section class="mf-panel">
    <h2 class="order-contacts__title">Контакты и получение</h2>
    <dl class="order-contacts">
      <div class="order-contacts__row">
        <dt>Покупатель</dt>
        <dd>{{ buyer.name }}</dd>
      </div>
      <div class="order-contacts__row">
        <dt>Телефон</dt>
        <dd>{{ formatPhone(buyer.phone) }}</dd>
      </div>
      <div class="order-contacts__row">
        <dt>Email</dt>
        <dd>{{ buyer.email }}</dd>
      </div>
      <div class="order-contacts__row">
        <dt>Канал чека</dt>
        <dd>{{ buyer.receiptChannel === 'max' ? 'MAX · демонстрация' : 'Email · демонстрация' }}</dd>
      </div>
      <div v-if="buyer.comment" class="order-contacts__row">
        <dt>Комментарий</dt>
        <dd>{{ buyer.comment }}</dd>
      </div>
    </dl>
    <p class="mf-muted order-contacts__note">
      Печатные фотографии получают в учреждении. Актуальные сроки и состояние указаны в заказе. По вопросам используйте форму помощи ниже.
    </p>
  </section>
</template>
<style scoped>
.order-contacts__title {
  font-size: 20px;
}
.order-contacts {
  display: grid;
  gap: 16px;
  margin: 24px 0;
  font-size: 14px;
  line-height: 1.6;
}
.order-contacts__row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 14px;
}
.order-contacts__row dt {
  color: var(--mf-color-text-secondary);
}
.order-contacts__row dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.order-contacts__note {
  font-size: 13px;
  line-height: 1.7;
}
</style>
