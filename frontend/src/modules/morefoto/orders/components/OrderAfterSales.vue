<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue';
import type { OrderSnapshot } from '../types';
import { useOrderDelivery } from '../composables/useOrderDelivery';
import OrderDownloads from './OrderDownloads.vue';
import OrderReceipt from './OrderReceipt.vue';
import DemoDeliveryControls from './DemoDeliveryControls.vue';
const props = defineProps<{ order: OrderSnapshot }>();
const { access, delivery, busy, error, notice, receiptChannel, maxAvailable, download, resend } = useOrderDelivery(() => props.order);
const feedback = useTemplateRef<HTMLElement>('feedback');
watch(error, async (value) => {
  if (value) {
    await nextTick();
    feedback.value?.focus();
  }
});
</script>
<template>
  <div class="after-sales">
    <div v-if="error" ref="feedback" tabindex="-1" role="alert" class="after-sales__error" data-testid="delivery-error">
      {{ error }}
    </div>
    <v-alert v-if="notice" type="success" variant="tonal" role="status" data-testid="delivery-notice">{{ notice }}</v-alert>
    <OrderDownloads :order="order" :access="access" :busy="busy" @download="download" />
    <OrderReceipt
      v-model:channel="receiptChannel"
      :delivery="delivery"
      :busy="busy"
      :max-available="maxAvailable"
      :can-send-files="access.state === 'available'"
      @resend="resend"
    />
    <DemoDeliveryControls :order="order" :busy="!!busy" />
  </div>
</template>
<style scoped>
.after-sales {
  display: grid;
  gap: var(--mf-space-6);
  min-width: 0;
}
.after-sales__error {
  padding: var(--mf-space-4);
  background: rgba(var(--v-theme-error), 0.08);
  color: rgb(var(--v-theme-error));
  border-radius: var(--mf-radius-field);
  line-height: 1.6;
}
</style>
