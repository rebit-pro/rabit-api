<script setup lang="ts">
import { computed } from 'vue';
import type { StaffCommand, HandoffWorkspace, HandoffErrors } from '../types';
const props = defineProps<{ command: StaffCommand; data: HandoffWorkspace; errors: HandoffErrors }>();
const emit = defineEmits<{ change: [value: Partial<StaffCommand>] }>();
const shoots = computed(() => props.data.scope.shoots.filter((s) => s.institutionId === props.command.institutionId));
const groups = computed(() => props.data.groups.filter((g) => g.shootId === props.command.shootId && g.kind === 'regular'));
const row = (index: number, patch: { code?: string; groupId?: string }) =>
  emit('change', { rows: props.command.rows.map((r, i) => (i === index ? { ...r, ...patch } : r)) });
function shoot(id: string) {
  emit('change', { shootId: id, rows: props.command.rows.map((r) => ({ ...r, groupId: '', code: '' })) });
}
function institution(id: string) {
  emit('change', { institutionId: id, shootId: '', rows: props.command.rows.map((r) => ({ ...r, groupId: '', code: '' })) });
}
function add() {
  emit('change', { rows: [...props.command.rows, { id: crypto.randomUUID(), groupId: groups.value[0]?.id ?? '', code: '' }] });
}
</script>
<template>
  <template v-if="command.action === 'submit'">
    <p class="mb-5">
      Укажите исходную группу и код ребёнка или любого его снимка. Куратор проверит и перенесёт весь набор в папку сотрудников этой съёмки.
    </p>
    <v-select
      :model-value="command.institutionId"
      label="Учреждение списка"
      aria-label="Учреждение списка"
      :items="data.scope.institutions"
      item-title="name"
      item-value="id"
      :disabled="!!command.id"
      @update:model-value="institution"
    />
    <v-select
      :model-value="command.shootId"
      label="Съёмка списка"
      aria-label="Съёмка списка"
      :items="shoots"
      item-title="name"
      item-value="id"
      :disabled="!!command.id"
      @update:model-value="shoot"
    />
    <p v-if="errors.rows" role="alert">{{ errors.rows }}</p>
    <section v-for="(item, index) in command.rows" :key="item.id" class="handoff-row" :data-testid="'request-row-' + index">
      <h3>Ребёнок {{ index + 1 }}</h3>
      <v-select
        :model-value="item.groupId"
        :label="'Исходная группа ' + (index + 1)"
        :aria-label="'Исходная группа ' + (index + 1)"
        :items="groups"
        item-title="name"
        item-value="id"
        :error-messages="errors['group:' + item.id]"
        :aria-invalid="!!errors['group:' + item.id]"
        @update:model-value="row(index, { groupId: $event, code: '' })"
      />
      <v-text-field
        :model-value="item.code"
        :label="'Код ребёнка или снимка ' + (index + 1)"
        placeholder="Например, A или A001"
        :error-messages="errors['code:' + item.id]"
        :aria-invalid="!!errors['code:' + item.id]"
        @update:model-value="row(index, { code: $event })"
      />
      <v-btn
        variant="text"
        :disabled="command.rows.length === 1"
        :aria-label="'Удалить строку ' + (index + 1)"
        @click="emit('change', { rows: command.rows.filter((r) => r.id !== item.id) })"
        >Удалить строку</v-btn
      >
    </section>
    <v-btn variant="outlined" class="mb-5" :disabled="command.rows.length >= 30 || !command.shootId" @click="add">Добавить ребёнка</v-btn>
    <v-textarea
      :model-value="command.comment"
      label="Комментарий к списку"
      rows="3"
      counter="500"
      :error-messages="errors.comment"
      :aria-invalid="!!errors.comment"
      @update:model-value="emit('change', { comment: $event })"
    />
    <p class="mf-muted">
      Цены и условия папки сотрудников назначает организатор. Передача списка сама по себе не меняет фотографии и заказы.
    </p>
  </template>
  <v-textarea
    v-else-if="command.action === 'clarify'"
    :model-value="command.reason"
    label="Что нужно уточнить"
    rows="4"
    counter="500"
    :error-messages="errors.reason"
    :aria-invalid="!!errors.reason"
    @update:model-value="emit('change', { reason: $event })"
  />
  <v-checkbox
    v-if="command.action === 'clarify'"
    :model-value="command.confirmed"
    label="Подтверждаю запрос уточнения и сохранение причины в истории"
    :error-messages="errors.confirmed"
    :aria-invalid="!!errors.confirmed"
    @update:model-value="emit('change', { confirmed: !!$event })"
  />
</template>
