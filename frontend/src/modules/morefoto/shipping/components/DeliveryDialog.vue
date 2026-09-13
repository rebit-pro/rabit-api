<script setup lang="ts">
import { packCount, printCountLabel } from '../display';
import { computed } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import { formatMoment } from '../../handoff/display';
import type { DeliveryCommand, DeliveryErrors, DeliveryGroup, TransferBatch } from '../types';
const props = defineProps<{
  command: DeliveryCommand | null;
  selected: DeliveryGroup | TransferBatch | undefined;
  busy: boolean;
  error: string;
  errors: DeliveryErrors;
  restored: boolean;
}>();
const emit = defineEmits<{ close: []; save: []; reset: []; update: [patch: Partial<DeliveryCommand>] }>();
const title = computed(() =>
  props.command?.kind === 'transfer'
    ? 'Передача в учреждение'
    : props.command?.kind === 'unready'
      ? 'Снятие готовности'
      : 'Подтверждение готовности'
);
const label = computed(() =>
  props.command?.kind === 'transfer'
    ? 'Подтвердить передачу'
    : props.command?.kind === 'unready'
      ? 'Снять готовность'
      : 'Подтвердить готовность'
);
</script>
<template>
  <AdminDialog
    :open="!!command"
    :title="title"
    :busy="busy"
    :error="error"
    :restored="restored"
    :save-label="label"
    focus-heading
    @close="emit('close')"
    @save="emit('save')"
    @reset="emit('reset')"
    ><template v-if="command"
      ><div class="delivery-editor">
        <v-alert v-if="selected?.signature !== command.signature" type="warning" variant="tonal"
          >Состав или версия изменились. Повтор потерянного ответа безопасен; для нового действия загрузите актуальные данные.</v-alert
        >
        <template v-if="selected"
          ><p>
            <strong>{{ selected.institutionName }}</strong> · {{ selected.shootName }}
          </p>
          <template v-if="'groups' in selected"
            ><p>{{ packCount(selected.packs) }} · {{ printCountLabel(selected.prints) }}</p>
            <ul class="delivery-editor-groups">
              <li v-for="g in selected.groups" :key="g.id">
                {{ g.name }}{{ g.kind === 'staff' && g.name !== 'Сотрудники' ? ' · Сотрудники' : '' }}: {{ packCount(g.packs) }}. Срок —
                {{ formatMoment(g.deadline) }}.
              </li>
            </ul>
            <p class="mf-muted">Сверьте все перечисленные пакеты и подтвердите фактическое получение учреждением.</p></template
          ><template v-else
            ><p>{{ selected.name }} · {{ selected.jobNumber }} · версия {{ selected.version }}</p>
            <p class="mf-muted">
              {{
                command.kind === 'unready'
                  ? 'Укажите причину возврата к комплектации. История задания сохранится.'
                  : 'Все пакеты текущей версии сверены. После подтверждения можно записать передачу.'
              }}
            </p></template
          ></template
        >
        <template v-if="command.kind !== 'unready'"
          ><v-text-field
            label="Дата и время (МСК)"
            type="datetime-local"
            :model-value="command.date"
            :disabled="busy"
            :error-messages="errors.date"
            @update:model-value="emit('update', { date: $event })" /><v-text-field
            label="Ответственный"
            :model-value="command.responsible"
            :maxlength="100"
            :disabled="busy"
            :error-messages="errors.responsible"
            @update:model-value="emit('update', { responsible: $event })"
        /></template>
        <v-text-field
          v-if="command.kind === 'transfer'"
          label="Принял в учреждении"
          :model-value="command.receiver"
          :maxlength="100"
          :disabled="busy"
          :error-messages="errors.receiver"
          @update:model-value="emit('update', { receiver: $event })"
        />
        <v-textarea
          :label="command.kind === 'unready' ? 'Причина снятия готовности' : 'Комментарий к действию'"
          :model-value="command.comment"
          :maxlength="500"
          counter="500"
          rows="3"
          auto-grow
          :disabled="busy"
          :error-messages="errors.comment"
          @update:model-value="emit('update', { comment: $event })"
        />
        <v-checkbox
          label="Состав и фактическое событие проверены"
          :model-value="command.confirmed"
          :disabled="busy"
          :error-messages="errors.confirmed"
          @update:model-value="emit('update', { confirmed: !!$event })"
        /></div></template
  ></AdminDialog>
</template>
<style scoped>
.delivery-editor {
  display: grid;
  gap: 20px;
  min-width: 0;
  overflow-wrap: anywhere;
}
.delivery-editor-groups {
  display: grid;
  gap: 10px;
  padding-left: 20px;
}
</style>
