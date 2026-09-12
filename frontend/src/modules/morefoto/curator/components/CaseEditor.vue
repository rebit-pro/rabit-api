<script setup lang="ts">
import type { CaseCommand, CaseErrors, CaseItem } from '../types';
import AdminDialog from '../../management/components/AdminDialog.vue';
import CaseFields from './CaseFields.vue';
import ExtensionFields from './ExtensionFields.vue';
defineProps<{
  command: CaseCommand | null;
  item: CaseItem | undefined;
  busy: boolean;
  error: string;
  errors: CaseErrors;
  restored: boolean;
}>();
defineEmits<{ close: []; save: []; reset: []; change: [value: Partial<CaseCommand>] }>();
</script>
<template>
  <AdminDialog
    :open="!!command"
    :title="command?.action === 'extend' ? 'Продлить приём заказов' : 'Ответ и состояние обращения'"
    :save-label="command?.action === 'extend' ? 'Подтвердить продление' : 'Сохранить ответ'"
    :busy="busy"
    :error="error"
    :restored="restored"
    @close="$emit('close')"
    @save="$emit('save')"
    @reset="$emit('reset')"
    ><p v-if="item" class="mb-5">
      <strong>{{ item.request.number }}</strong> · заказ {{ item.order.number }}
    </p>
    <CaseFields v-if="command?.action === 'reply'" :command="command" :errors="errors" @change="$emit('change', $event)" /><ExtensionFields
      v-if="command?.action === 'extend' && item"
      :command="command"
      :errors="errors"
      :period="item.order.period"
      :group-name="item.order.groupName"
      @change="$emit('change', $event)"
  /></AdminDialog>
</template>
