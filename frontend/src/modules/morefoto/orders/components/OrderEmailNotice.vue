<script setup lang="ts">
import { computed, onScopeDispose, shallowRef, toRef } from 'vue';
import { useOrderEmail } from '../composables/useOrderEmail';
import { orderEmailText, resendWait } from '../live/order-email-rules';
const props = defineProps<{ orderKey: string }>();
const { summary, busy, error, resend } = useOrderEmail(toRef(props, 'orderKey'));
// The wait label follows the clock without reloading the summary.
const now = shallowRef(Date.now());
const timer = setInterval(() => (now.value = Date.now()), 30000);
onScopeDispose(() => clearInterval(timer));
const notice = computed(() => (summary.value ? orderEmailText(summary.value) : null));
const wait = computed(() => (summary.value ? resendWait(summary.value, now.value) : ''));
</script>
<template>
  <div v-if="summary && notice" class="order-email" data-testid="order-email">
    <p class="order-email__text" :class="'order-email__text--' + notice.tone" role="status" data-testid="order-email-text">
      <v-icon icon="mdi-email-outline" size="18" aria-hidden="true" />{{ notice.text }}
    </p>
    <v-btn
      v-if="summary.canResend"
      block
      variant="tonal"
      color="primary"
      :loading="busy"
      :disabled="busy || !!wait"
      data-testid="order-email-resend"
      @click="resend"
      >{{ summary.latest ? 'Отправить ещё раз' : 'Отправить на почту' }}</v-btn
    >
    <p v-if="wait" class="order-email__hint" data-testid="order-email-wait">{{ wait }}</p>
    <p v-if="error" class="order-email__error" role="alert">{{ error }}</p>
  </div>
</template>
<style scoped>
.order-email {
  display: grid;
  gap: 8px;
  margin: 12px 0;
}
.order-email__text {
  display: flex;
  gap: 6px;
  align-items: flex-start;
  font-size: var(--mf-text-sm);
  line-height: 1.45;
}
.order-email__text .v-icon {
  flex: none;
  margin-top: 1px;
}
.order-email__text--success {
  color: var(--mf-tone-success-fg);
}
.order-email__text--warning {
  color: var(--mf-tone-warning-fg);
}
.order-email__hint,
.order-email__error {
  font-size: var(--mf-text-xs);
  color: var(--mf-color-text-secondary);
}
.order-email__error {
  color: var(--mf-tone-danger-fg);
}
</style>
