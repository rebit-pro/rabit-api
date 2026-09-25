<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
import SettlementPanel from '../../settlement/components/SettlementPanel.vue';
import { lateDecision } from '../../settlement/rules';
import { computed } from 'vue';
import type { StaffOrder } from '../types';
import { money } from '../../commerce/money';
import { formatMoment } from '../../handoff/display';
import { paymentLabels, productionLabels } from '../../orders/formatters';
import { downloadAccess } from '../../orders/delivery/rules';
import OrderComposition from '../../orders/components/OrderComposition.vue';
import OrderContacts from '../../orders/components/OrderContacts.vue';
import PaymentHistory from '../../orders/components/PaymentHistory.vue';
import OrderPeriod from './OrderPeriod.vue';
import { supportStatuses } from '../rules';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { paymentTone, productionTone } from '../../ui/statusTone';
const props = defineProps<{ order: StaffOrder }>();
const fileState = computed(() =>
  downloadAccess({ ...props.order, accessKey: '', requestId: '', galleryToken: '' }, props.order.period.now)
);
const fileLabels = {
  unpaid: 'После оплаты',
  revoked: 'Выдача прекращена по возврату',
  refund: 'Согласован возврат',
  review: 'Нужна проверка поздней оплаты',
  empty: 'Нет электронных файлов',
  expired: 'Срок получения истёк',
  available: 'Доступны покупателю'
};
</script>
<template>
  <div class="work-detail" data-testid="staff-order-detail">
    <header class="mf-panel">
      <h2>Заказ {{ order.number }}</h2>
      <p>{{ order.institutionName }} · {{ order.shootName }} · {{ order.groupName }}</p>
      <p class="mf-muted">{{ formatMoment(order.createdAt) }}</p>
      <div class="work-statuses">
        <strong class="work-total">{{ money(order.quote.total) }}</strong
        ><MfStatus :tone="toneOf(paymentTone, order.paymentStatus)">{{ paymentLabels[order.paymentStatus] }}</MfStatus
        ><MfStatus :tone="toneOf(productionTone, order.productionStatus)">{{ productionLabels[order.productionStatus] }}</MfStatus>
      </div>
      <p v-if="order.latePayment && !lateDecision(order)" class="mt-4">Поздняя оплата: требуется согласование исполнения.</p>
      <p class="mt-4">
        Электронные файлы: {{ fileLabels[fileState.state]
        }}<template v-if="fileState.deadline"> · срок до {{ formatMoment(fileState.deadline) }}</template>
      </p>
      <v-btn class="mt-4" variant="outlined" :to="'/cabinet/production/' + order.groupId">Производство группы</v-btn>
    </header>
    <SettlementPanel
      :order="order"
      editable
      :support-id="typeof route.query.support === 'string' ? route.query.support : undefined"
    /><OrderPeriod :period="order.period" /><OrderComposition :quote="order.quote" /><OrderContacts :order="order" /><PaymentHistory
      :attempts="order.paymentAttempts ?? []"
    />
    <section class="mf-panel">
      <h2>Обращения по заказу</h2>
      <p v-if="!order.supportRequests?.length" class="mf-muted mt-4">Обращений пока нет.</p>
      <div v-for="request in order.supportRequests" :key="request.id" class="work-case-link">
        <p>{{ request.number }} · {{ supportStatuses[request.status] }}</p>
        <v-btn :to="'/cabinet/support/' + request.id" variant="outlined">Открыть обращение</v-btn>
      </div>
    </section>
  </div>
</template>
