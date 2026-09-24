<script setup lang="ts">
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
import AdminDialog from '../../management/components/AdminDialog.vue';
import ContactCorrectionFields from './ContactCorrectionFields.vue';
import LineCorrectionFields from './LineCorrectionFields.vue';
import RefundFields from './RefundFields.vue';
import ResolutionFields from './ResolutionFields.vue';
import OperationConfirmation from './OperationConfirmation.vue';
defineProps<{ command: SaleCommand | null; order: SaleOrder; busy: boolean; error: string; errors: SaleErrors; restored: boolean }>();
defineEmits<{ close: []; save: []; reset: []; change: [value: Partial<SaleCommand>] }>();
const titles = {
  contacts: 'Исправить контакты',
  line: 'Исправить позицию',
  refund: 'Оформить возврат',
  result: 'Результат возврата',
  retry: 'Повторить возврат',
  fulfilment: 'Согласовать исполнение',
  recover: 'Повторное получение файлов'
};
</script>
<template>
  <AdminDialog
    :open="!!command"
    :title="command ? titles[command.action] : ''"
    :busy="busy"
    :error="error"
    :restored="restored"
    save-label="Подтвердить операцию"
    @close="$emit('close')"
    @save="$emit('save')"
    @reset="$emit('reset')"
  >
    <template v-if="command"
      ><p class="mb-5">
        <strong>{{ order.number }}</strong> · {{ order.groupName }}
      </p>
      <ContactCorrectionFields
        v-if="command.action === 'contacts' || command.action === 'recover'"
        :command="command"
        :order="order"
        :errors="errors"
        @change="$emit('change', $event)"
      />
      <LineCorrectionFields
        v-if="command.action === 'line'"
        :command="command"
        :order="order"
        :errors="errors"
        @change="$emit('change', $event)"
      />
      <RefundFields
        v-if="command.action === 'refund'"
        :command="command"
        :order="order"
        :errors="errors"
        @change="$emit('change', $event)"
      />
      <ResolutionFields
        v-if="['result', 'retry', 'fulfilment', 'recover'].includes(command.action)"
        :command="command"
        :order="order"
        :errors="errors"
        @change="$emit('change', $event)"
      />
      <OperationConfirmation :command="command" :order="order" :errors="errors" @change="$emit('change', $event)" />
    </template>
  </AdminDialog>
</template>
<style>
.sale-fields {
  display: grid;
  gap: 20px;
  margin: 20px 0;
  min-width: 0;
}
.sale-fields p {
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.sale-preview {
  background: var(--mf-color-bg);
  padding: 20px;
  border-radius: 4px;
  display: grid;
  gap: 12px;
}
.sale-fields .v-select__selection-text {
  white-space: normal;
  overflow-wrap: anywhere;
}
.sale-fields .v-chip {
  max-width: 100%;
}
.sale-fields .v-chip__content {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
