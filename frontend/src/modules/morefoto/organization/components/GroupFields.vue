<script setup lang="ts">
import { computed } from 'vue';
import type { EntityFields, FieldErrors, StaffOption } from '../types';
const props = defineProps<{ fields: EntityFields; errors: FieldErrors; staff: StaffOption[]; busy: boolean; editing: boolean }>();
defineEmits<{ patch: [value: Partial<EntityFields>] }>();
const teachers = computed(() => [
  { title: 'Не назначен', value: null },
  ...props.staff.filter((item) => item.role === 'teacher').map((item) => ({ title: item.name + ' · ' + item.email, value: item.id }))
]);
const kinds = [
  { title: 'Группа / класс', value: 'regular' },
  { title: 'Сотрудники', value: 'staff' }
];
</script>
<template>
  <v-text-field
    :model-value="fields.name"
    label="Название группы"
    aria-label="Название группы"
    required
    maxlength="120"
    :disabled="busy"
    :error-messages="errors.name"
    :aria-invalid="!!errors.name"
    @update:model-value="$emit('patch', { name: $event ?? '' })"
  />
  <v-select
    :model-value="fields.groupKind"
    :items="kinds"
    label="Тип группы"
    aria-label="Тип группы"
    data-testid="org-kind"
    :disabled="busy"
    :readonly="editing"
    :error-messages="errors.groupKind"
    :aria-invalid="!!errors.groupKind"
    @update:model-value="$emit('patch', { groupKind: $event })"
  />
  <v-select
    :model-value="fields.teacherId"
    :items="teachers"
    label="Ответственный группы"
    aria-label="Ответственный группы"
    data-testid="org-teacher"
    :disabled="busy"
    :error-messages="errors.teacherId"
    :aria-invalid="!!errors.teacherId"
    @update:model-value="$emit('patch', { teacherId: $event })"
  />
  <p class="mf-muted">Ответственный увидит только назначенные ему группы. Новая группа появится в галерее со статусом «Подготовка».</p>
</template>
