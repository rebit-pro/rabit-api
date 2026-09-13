<script setup lang="ts">
import { useOrder } from '../composables/useOrder';
import PaymentPanel from './PaymentPanel.vue';
const { order, loading, error, unavailable, reload } = useOrder();
</script>
<template>
  <main class="mf-main payment-page">
    <v-skeleton-loader v-if="loading" type="heading, article" />
    <section v-else-if="error" class="mf-panel mf-empty">
      <h1>{{ unavailable ? 'Оплата недоступна' : 'Не удалось открыть оплату' }}</h1>
      <p class="mf-muted my-5">{{ error }}</p>
      <v-btn v-if="!unavailable" color="primary" @click="reload">Повторить загрузку</v-btn>
    </section>
    <template v-else-if="order">
      <RouterLink :to="'/orders/access/' + order.accessKey" class="mf-back">← К своему заказу</RouterLink>
      <header class="mf-page-heading">
        <p class="mf-eyebrow">ЗАКАЗ {{ order.number }}</p>
        <h1>Демонстрационная оплата</h1>
        <p class="mf-muted">{{ order.institutionName }} · {{ order.groupName }} · {{ order.shootName }}</p>
      </header>
      <PaymentPanel :key="order.id" :order="order" />
    </template>
  </main>
</template>
<style scoped>
.payment-page {
  width: 100%;
  min-height: 100svh;
}
</style>
