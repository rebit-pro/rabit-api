<script setup lang="ts">
import { computed } from 'vue';
import type { EntityFields, FieldErrors, StaffOption } from '../types';
const props = defineProps<{ fields: EntityFields; errors: FieldErrors; staff: StaffOption[]; busy: boolean }>();
defineEmits<{ patch: [value: Partial<EntityFields>] }>();
const curators = computed(() => [
  { title: 'Не назначен', value: null },
  ...props.staff.filter((item) => item.role === 'curator').map((item) => ({ title: item.name + ' · ' + item.email, value: item.id }))
]);
const heads = computed(() => [
  { title: 'Не назначен', value: null },
  ...props.staff.filter((item) => item.role === 'head').map((item) => ({ title: item.name + ' · ' + item.email, value: item.id }))
]);
</script>
<template>
  <v-text-field
    :model-value="fields.name"
    label="Название учреждения"
    aria-label="Название учреждения"
    required
    maxlength="120"
    :disabled="busy"
    :error-messages="errors.name"
    :aria-invalid="!!errors.name"
    @update:model-value="$emit('patch', { name: $event ?? '' })"
  />
  <v-textarea
    :model-value="fields.address"
    label="Адрес учреждения"
    aria-label="Адрес учреждения"
    required
    rows="2"
    auto-grow
    maxlength="240"
    :disabled="busy"
    :error-messages="errors.address"
    :aria-invalid="!!errors.address"
    @update:model-value="$emit('patch', { address: $event ?? '' })"
  />
  <v-select
    :model-value="fields.curatorId"
    :items="curators"
    label="Куратор учреждения"
    aria-label="Куратор учреждения"
    data-testid="org-curator"
    :disabled="busy"
    :error-messages="errors.curatorId"
    :aria-invalid="!!errors.curatorId"
    @update:model-value="$emit('patch', { curatorId: $event })"
  />
  <v-select
    :model-value="fields.headId"
    :items="heads"
    label="Руководитель учреждения"
    aria-label="Руководитель учреждения"
    data-testid="org-head"
    :disabled="busy"
    :error-messages="errors.headId"
    :aria-invalid="!!errors.headId"
    @update:model-value="$emit('patch', { headId: $event })"
  />
  <p class="mf-muted">Назначенные сотрудники увидят учреждение и его съёмки в своём кабинете.</p>
</template>
