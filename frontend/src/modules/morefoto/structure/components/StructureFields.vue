<script setup lang="ts">
import { computed } from 'vue';
import type { FieldErrors, StructureFields, StructureKind } from '../model';
const model = defineModel<StructureFields>({ required: true });
/** Shoot of a group chosen on the institution page; the shoot page passes no shoots and keeps its own. */
const parentId = defineModel<string | null>('parentId', { default: null });
const props = defineProps<{
  kind: StructureKind;
  existing: boolean;
  errors: FieldErrors;
  shoots?: { title: string; value: string }[];
}>();
const nameLabel = computed(
  () =>
    ({
      institution: 'Название учреждения',
      shoot: 'Название съёмки',
      group: 'Название группы'
    })[props.kind]
);
const kinds = [
  { title: 'Обычная группа', value: 'regular' },
  { title: 'Сотрудники', value: 'staff' }
];
</script>
<template>
  <v-select
    v-if="kind === 'group' && shoots"
    v-model="parentId"
    :items="shoots"
    label="Съёмка"
    aria-label="Съёмка"
    :disabled="existing"
    :hint="existing ? 'Группа остаётся в своей съёмке.' : 'Группа появится в выбранной съёмке.'"
    persistent-hint
    class="mb-4"
    data-testid="group-shoot"
  />
  <v-text-field
    v-model="model.name"
    :label="nameLabel"
    :aria-label="nameLabel"
    :error-messages="errors.name"
    :aria-invalid="!!errors.name"
    maxlength="255"
  />
  <v-textarea
    v-if="kind === 'institution'"
    v-model="model.address"
    label="Адрес"
    aria-label="Адрес"
    :error-messages="errors.address"
    :aria-invalid="!!errors.address"
    maxlength="500"
    rows="2"
    auto-grow
  />
  <v-text-field
    v-if="kind === 'shoot'"
    :model-value="model.date"
    @update:model-value="model.date = $event ?? ''"
    type="date"
    label="Дата съёмки"
    aria-label="Дата съёмки"
    :error-messages="errors.date"
    :aria-invalid="!!errors.date"
    hint="Можно оставить пустым, если дата ещё не назначена."
    persistent-hint
    clearable
    @click:clear="model.date = ''"
  />
  <v-select
    v-if="kind === 'group'"
    v-model="model.groupKind"
    :items="kinds"
    label="Тип группы"
    aria-label="Тип группы"
    :disabled="existing"
    hint="Тип созданной группы сохраняется."
    persistent-hint
  />
</template>
