<script setup lang="ts">
import type { CaseItem } from '../types';
import { supportTopics } from '../../orders/delivery/rules';
import { formatMoment } from '../../handoff/display';
import SupportTimeline from './SupportTimeline.vue';
import OrderPeriod from './OrderPeriod.vue';
defineProps<{ item: CaseItem }>();
defineEmits<{ reply: []; extend: [] }>();
</script>
<template>
  <div class="work-detail" data-testid="case-detail">
    <section class="mf-panel">
      <p class="mf-eyebrow">{{ item.request.number }}</p>
      <h2>{{ supportTopics.find((t) => t.value === item.request.topic)?.title }}</h2>
      <p class="mt-4">{{ item.order.institutionName }} · {{ item.order.groupName }} · {{ item.order.shootName }}</p>
      <p class="mf-muted mt-2">{{ formatMoment(item.request.createdAt) }}</p>
      <p v-if="item.request.photoCode" class="mt-4">Кадр {{ item.request.photoCode }}</p>
      <p class="case-original">{{ item.request.message }}</p>
      <p>Для ответа: {{ item.request.replyEmail }}</p>
      <v-btn :to="{ path: '/cabinet/orders/' + item.order.id, query: { support: item.request.id } }" variant="text" class="mt-4"
        >Заказ {{ item.order.number }}</v-btn
      ><SupportTimeline :request="item.request" :extensions="item.order.period.extensions" :settlement="item.order.settlement" />
      <div class="mf-actions mt-6">
        <v-btn @click="$emit('reply')">Ответ и состояние</v-btn
        ><v-btn v-if="item.request.status !== 'resolved'" variant="outlined" :disabled="!item.order.period.sentAt" @click="$emit('extend')"
          >Продлить приём</v-btn
        >
      </div>
      <p class="mf-muted mt-4">Ответ виден в истории этого обращения. Демонстрация не отправляет письма.</p>
    </section>
    <OrderPeriod :period="item.order.period" />
  </div>
</template>
