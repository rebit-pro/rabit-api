<script setup lang="ts">
import RelatedSettlement from '../../settlement/components/RelatedSettlement.vue';
import type { SettlementState } from '../../settlement/types';
import { computed } from 'vue';
import type { SupportRequest } from '../../orders/delivery/types';
import type { GroupExtension } from '../types';
import { supportStatuses } from '../rules';
import { formatMoment } from '../../handoff/display';
import { calendarDays } from '../../handoff/rules';
const props = defineProps<{ request: SupportRequest; extensions: GroupExtension[]; settlement?: SettlementState }>();
const events = computed(() =>
  [
    ...(props.request.history ?? []).map((e) => ({
      id: e.requestId,
      at: e.at,
      name: e.actorName,
      title: supportStatuses[e.status],
      text: e.comment,
      closing: null as string | null,
      previous: null as string | null
    })),
    ...props.extensions
      .filter((e) => e.supportId === props.request.id && e.orderId === props.request.orderId)
      .map((e) => ({
        id: e.id,
        at: e.at,
        name: e.actorName,
        title: 'Приём продлён',
        text: e.reason,
        closing: e.closesAt,
        previous: e.previousClosesAt
      }))
  ].sort((a, b) => Date.parse(a.at) - Date.parse(b.at))
);
</script>
<template>
  <div class="case-timeline">
    <p>
      <strong>{{ supportStatuses[request.status] }}</strong>
    </p>
    <ol v-if="events.length">
      <li v-for="event in events" :key="event.id">
        <strong>{{ event.title }}</strong>
        <p class="mf-muted">{{ event.name }} · {{ formatMoment(event.at) }}</p>
        <p class="case-message">{{ event.text }}</p>
        <template v-if="event.closing"
          ><p>Приём: {{ formatMoment(event.previous) }} → {{ formatMoment(event.closing) }}</p>
          <p>Доставка до {{ formatMoment(calendarDays(event.closing, 7)) }}</p></template
        >
      </li>
    </ol>
    <RelatedSettlement :state="settlement" :support-id="request.id" />
  </div>
</template>
<style scoped>
.case-timeline {
  margin-top: 16px;
  overflow-wrap: anywhere;
}
.case-timeline ol {
  padding-left: 20px;
}
.case-timeline li {
  padding: 16px 0;
  border-bottom: 1px solid #dce3e8;
}
.case-timeline li p {
  margin-top: 6px;
}
.case-message {
  white-space: pre-wrap;
  line-height: 1.6;
}
</style>
