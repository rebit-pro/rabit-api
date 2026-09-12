<script setup lang="ts">
import { shallowRef } from 'vue';
import type { SaleOrder, SaleCommand } from '../types';
import { settlement, remaining } from '../rules';
import { saleCommand } from '../command';
import { useSettlementEditor } from '../useSettlementEditor';
import SettlementDialog from './SettlementDialog.vue';
import SettlementHistory from './SettlementHistory.vue';
const props = defineProps<{ order: SaleOrder; editable?: boolean; supportId?: string }>();
const notice = shallowRef('');
const editor = useSettlementEditor(() => {
  notice.value = 'Операция сохранена. Результат и последствия показаны в истории.';
});
const { command, busy, error, errors, restored } = editor;
function open(action: SaleCommand['action'], refundId = '') {
  editor.open(
    () => saleCommand(props.order, action, refundId, props.supportId ?? ''),
    props.order.id + ':' + action + ':' + refundId + ':' + (props.supportId ?? '')
  );
}
function change(patch: Partial<SaleCommand>) {
  if (command.value) Object.assign(command.value, patch);
}
</script>
<template>
  <section class="mf-panel sale-panel" data-testid="settlement-panel">
    <p class="mf-eyebrow">СОПРОВОЖДЕНИЕ ЗАКАЗА</p>
    <h2>Исправления и возвраты</h2>
    <p v-if="order.paymentStatus !== 'paid'" class="mf-muted mt-4">Действия куратора доступны после подтверждённой оплаты.</p>
    <div v-else-if="editable" class="mf-actions mt-6">
      <v-btn variant="outlined" @click="open('contacts')">Исправить контакты</v-btn
      ><v-btn variant="outlined" @click="open('line')">Исправить позицию</v-btn
      ><v-btn variant="outlined" :disabled="remaining(order) <= 0" @click="open('refund')">Оформить возврат</v-btn
      ><v-btn variant="outlined" @click="open('recover')">Повторное получение</v-btn
      ><v-btn
        v-if="order.latePayment || settlement(order).hold || (settlement(order).needsReprint && !settlement(order).reprintApproved)"
        @click="open('fulfilment')"
        >Согласовать исполнение</v-btn
      >
    </div>
    <p v-if="notice" role="status" class="mt-5">{{ notice }}</p>
    <SettlementHistory :order="order" :editable="editable" @edit="open" />
    <SettlementDialog
      v-if="editable"
      :command="command"
      :order="order"
      :busy="busy"
      :error="error"
      :errors="errors"
      :restored="restored"
      @change="change"
      @close="editor.close"
      @reset="editor.reset"
      @save="editor.save"
    />
  </section>
</template>
<style scoped>
.sale-panel {
  min-width: 0;
}
.sale-panel h2 {
  font-size: 22px;
  line-height: 1.5;
}
.sale-panel .mf-actions {
  gap: 12px;
  flex-wrap: wrap;
}
.sale-panel .mf-actions :deep(.v-btn) {
  max-width: 100%;
  height: auto;
  min-height: 46px;
  padding: 12px 16px;
}
.sale-panel :deep(.v-btn__content) {
  white-space: normal;
  text-align: center;
}
</style>
