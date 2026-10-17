<script setup lang="ts">
import { computed, toRef } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import { money } from '../../commerce/money';
import { formatMoment } from '../formatters';
import { useOrderReceipts } from '../composables/useOrderReceipts';
import { paymentModeLabels, receiptKindTitles, receiptStatusLabels, receiptText, receiptTones, receiptsHint } from '../live/receipt-rules';
const props = defineProps<{ orderKey: string }>();
const { receipts, loading, reload } = useOrderReceipts(toRef(props, 'orderKey'));
const hint = computed(() => (receipts.value ? receiptsHint(receipts.value.enabled, receipts.value.receipts) : null));
</script>
<template>
  <section v-if="receipts && hint" class="mf-panel order-receipts" aria-labelledby="order-receipts-title" data-testid="order-receipts">
    <header class="order-receipts__head">
      <h2 id="order-receipts-title">Чеки</h2>
      <v-btn icon="mdi-refresh" variant="text" density="compact" :loading="loading" aria-label="Обновить чеки" @click="reload" />
    </header>
    <p class="mf-muted order-receipts__hint">{{ hint }}</p>
    <article
      v-for="receipt in receipts.receipts"
      :key="receipt.id"
      class="order-receipt"
      :data-kind="receipt.kind"
      data-testid="order-receipt"
    >
      <div class="order-receipt__title">
        <strong>{{ receiptKindTitles[receipt.kind] }} · {{ money(receipt.amount) }}</strong>
        <MfStatus :tone="receiptTones[receipt.status]">{{ receiptStatusLabels[receipt.status] }}</MfStatus>
      </div>
      <ul class="order-receipt__items">
        <li v-for="(item, index) in receipt.items" :key="index">
          {{ item.description }} × {{ item.quantity }} — {{ money(item.amount * item.quantity) }} ·
          {{ paymentModeLabels[item.paymentMode] }}
        </li>
      </ul>
      <p class="order-receipt__text" :class="{ 'order-receipt__text--failed': receipt.status === 'failed' }">{{ receiptText(receipt) }}</p>
      <dl v-if="receipt.status === 'sent' && receipt.fiscalDocumentNumber" class="order-receipt__fiscal" data-testid="order-receipt-fiscal">
        <div v-if="receipt.registeredAt">
          <dt>Дата</dt>
          <dd>{{ formatMoment(receipt.registeredAt) }} мск</dd>
        </div>
        <div>
          <dt>ФН</dt>
          <dd>{{ receipt.fiscalStorageNumber }}</dd>
        </div>
        <div>
          <dt>ФД</dt>
          <dd>{{ receipt.fiscalDocumentNumber }}</dd>
        </div>
        <div>
          <dt>ФП</dt>
          <dd>{{ receipt.fiscalAttribute }}</dd>
        </div>
      </dl>
      <p v-if="receipt.handedAt" class="mf-muted order-receipt__handed">Печать выдана {{ formatMoment(receipt.handedAt) }} мск</p>
    </article>
  </section>
</template>
<style scoped>
.order-receipts {
  display: grid;
  gap: 12px;
}
.order-receipts__head,
.order-receipt__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.order-receipts__head h2 {
  font-size: 20px;
}
.order-receipts__hint,
.order-receipt__handed {
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.order-receipt {
  display: grid;
  gap: 8px;
  padding: 16px;
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
}
.order-receipt__items {
  margin: 0 0 0 20px;
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.order-receipt__text {
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.order-receipt__text--failed {
  color: var(--mf-tone-danger-fg);
}
.order-receipt__fiscal {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 20px;
  margin: 0;
  font-size: var(--mf-text-sm);
}
.order-receipt__fiscal div {
  display: flex;
  gap: 6px;
}
.order-receipt__fiscal dt {
  color: var(--mf-color-text-secondary);
}
.order-receipt__fiscal dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
</style>
