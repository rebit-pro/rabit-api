<script setup lang="ts">
import type { BuyerDelivery } from '../delivery/types';
import type { ReceiptChannel } from '../types';
import { formatMoment } from '../formatters';
const channel = defineModel<ReceiptChannel>('channel', { required: true });
defineProps<{
  delivery: BuyerDelivery | null;
  busy: string;
  maxAvailable: boolean;
  canSendFiles: boolean;
}>();
defineEmits<{ resend: [target: 'receipt' | 'filesEmail'] }>();
</script>
<template>
  <section class="mf-panel receipt" aria-labelledby="receipt-title" data-testid="order-receipt">
    <h2 id="receipt-title">Чек и письмо со ссылкой</h2>
    <p v-if="!delivery" class="mf-muted mt-3">Чек появится после подтверждённой оплаты.</p>
    <template v-else>
      <p class="mf-muted receipt__note mt-3">
        Проверка доставки на тестовых данных. Фискальный чек не формируется, настоящие сообщения не отправляются.
      </p>
      <p class="mt-4" role="status" data-testid="receipt-status">
        {{ delivery.receipt.status === 'sent' ? 'Отправка чека имитирована' : 'Ошибка доставки чека' }}
        · {{ delivery.receipt.channel === 'max' ? 'MAX' : 'Email' }}
      </p>
      <p class="mf-muted receipt__note mt-1">{{ formatMoment(delivery.receipt.at) }} мск</p>
      <v-radio-group v-model="channel" label="Канал повторной отправки чека" :disabled="!!busy" class="mt-4" hide-details>
        <v-radio label="Email для чека" value="email" />
        <v-radio :label="maxAvailable ? 'MAX для чека' : 'MAX для чека — недоступен'" value="max" :disabled="!maxAvailable" />
      </v-radio-group>
      <p v-if="channel === 'max' && !maxAvailable" class="mf-muted receipt__note mt-2">Выберите email: MAX сейчас недоступен.</p>
      <v-btn
        color="primary"
        variant="outlined"
        :loading="busy === 'receipt'"
        :disabled="!!busy || (channel === 'max' && !maxAvailable)"
        class="mt-4"
        @click="$emit('resend', 'receipt')"
        >Повторить отправку чека</v-btn
      >
      <div v-if="delivery.filesEmail" class="receipt__files">
        <h3 class="receipt__subtitle">Ссылка на фотографии</h3>
        <p class="mt-2" role="status" data-testid="files-email-status">
          {{ delivery.filesEmail.status === 'sent' ? 'Отправка письма имитирована' : 'Ошибка доставки письма' }}
          · Email
        </p>
        <p class="mf-muted receipt__note mt-1">{{ formatMoment(delivery.filesEmail.at) }} мск</p>
        <v-btn
          v-if="canSendFiles"
          variant="outlined"
          color="primary"
          :loading="busy === 'filesEmail'"
          :disabled="!!busy"
          class="mt-4"
          @click="$emit('resend', 'filesEmail')"
          >Повторить письмо со ссылкой</v-btn
        >
        <p v-else class="mf-muted receipt__note mt-3">Повтор письма доступен, пока открыт срок скачивания.</p>
      </div>
      <p class="mf-muted receipt__note mt-4">
        Используется email из контактов заказа. Если адрес указан неверно, оставьте Рите обращение с правильным адресом для ответа.
      </p>
      <a href="#order-help" class="receipt__help">Письмо не пришло или ошибка в адресе</a>
    </template>
  </section>
</template>
<style scoped>
.receipt {
  min-width: 0;
  overflow-wrap: anywhere;
}
.receipt__note {
  font-size: var(--mf-text-small);
}
.receipt__files {
  margin-top: var(--mf-space-6);
  padding-top: var(--mf-space-6);
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.receipt__subtitle {
  font-size: var(--mf-text-control);
}
.receipt__help {
  display: inline-flex;
  align-items: center;
  min-height: var(--mf-touch-size);
  margin-top: var(--mf-space-2);
  color: rgb(var(--v-theme-primary));
}
</style>
