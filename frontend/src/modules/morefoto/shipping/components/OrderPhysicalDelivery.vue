<script setup lang="ts">
import type { OrderSnapshot } from '../../orders/types';
import { formatMoment } from '../../handoff/display';
defineProps<{ order: OrderSnapshot }>();
</script>
<template>
  <section v-if="order.physicalDelivery" class="mf-panel" aria-labelledby="physical-delivery-title" data-testid="physical-delivery">
    <p class="mf-eyebrow">ПЕЧАТНЫЕ ФОТОГРАФИИ</p>
    <h2 id="physical-delivery-title" class="mt-2">
      {{ order.physicalDelivery.transferredAt ? 'Заказ передан в учреждение' : 'Заказ готов к передаче' }}
    </h2>
    <p v-if="order.physicalDelivery.transferredAt" class="mt-3">
      {{ formatMoment(order.physicalDelivery.transferredAt) }} · {{ order.institutionName }}. Фотографии упакованы в отдельный пакет.
    </p>
    <p v-else class="mt-3">
      Пакет с вашими фотографиями скомплектован {{ formatMoment(order.physicalDelivery.readyAt ?? null) }}. Следующий этап — доставка в
      {{ order.institutionName }}.
    </p>
    <p class="mf-muted mt-3">Получение фотографий согласуйте с представителем учреждения.</p>
  </section>
</template>
