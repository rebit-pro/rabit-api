<script setup lang="ts">
import type { EntityFields, FieldErrors } from '../types';
defineProps<{ fields: EntityFields; errors: FieldErrors; busy: boolean }>();
defineEmits<{ patch: [value: Partial<EntityFields>] }>();
</script>
<template>
  <v-text-field
    :model-value="fields.name"
    label="Название съёмки"
    aria-label="Название съёмки"
    required
    maxlength="120"
    :disabled="busy"
    :error-messages="errors.name"
    :aria-invalid="!!errors.name"
    @update:model-value="$emit('patch', { name: $event ?? '' })"
  />
  <v-text-field
    :model-value="fields.date"
    type="date"
    label="Дата съёмки"
    aria-label="Дата съёмки"
    required
    min="2000-01-01"
    max="2100-12-31"
    :disabled="busy"
    :error-messages="errors.date"
    :aria-invalid="!!errors.date"
    @update:model-value="$emit('patch', { date: $event ?? '' })"
  />
  <p class="mf-muted">У новой съёмки будут собственные группы и ссылки. Предыдущие съёмки сохранятся.</p>
</template>
