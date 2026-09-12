<script setup lang="ts">
import { computed, ref, useId, useTemplateRef, watch } from 'vue';
import type { UiDensity } from '../types';
import { quantityValue } from '../field-values';
const props = withDefaults(
  defineProps<{
    modelValue: string | number | null;
    label?: string;
    context?: string;
    min?: number;
    max?: number;
    disabled?: boolean;
    readonly?: boolean;
    density?: UiDensity;
  }>(),
  { label: 'Количество', context: '', min: 1, max: 99, density: 'comfortable' }
);
const emit = defineEmits<{ 'update:modelValue': [value: string | number]; commit: [value: number] }>();
const id = 'quantity-' + useId();
const input = useTemplateRef<{ focus: () => void }>('input');
const draft = ref(String(props.modelValue ?? ''));
const touched = ref(false);
const value = computed(() => quantityValue(draft.value, props.min, props.max));
const label = computed(() => props.label + (props.context ? ' — ' + props.context : ''));
const error = computed(() => (touched.value && value.value === null ? 'Целое число от ' + props.min + ' до ' + props.max + '.' : ''));
watch(
  () => props.modelValue,
  (next) => {
    draft.value = String(next ?? '');
  }
);
watch(
  () => props.disabled,
  (disabled) => {
    if (!disabled) {
      draft.value = String(props.modelValue ?? '');
      touched.value = false;
    }
  }
);
function update(next: string | number | null) {
  draft.value = String(next ?? '');
  emit('update:modelValue', draft.value);
}
function validate(): boolean {
  touched.value = true;
  if (value.value === null) {
    input.value?.focus();
    return false;
  }
  return true;
}
function commit() {
  touched.value = true;
  if (!props.disabled && !props.readonly && value.value !== null) emit('commit', value.value);
}
function step(direction: number) {
  if (props.disabled || props.readonly || value.value === null) return;
  const next = value.value + direction;
  if (next < props.min || next > props.max) return;
  draft.value = String(next);
  touched.value = false;
  emit('update:modelValue', next);
  emit('commit', next);
}
defineExpose({ validate });
</script>
<template>
  <div class="ui-quantity" :class="{ 'ui-quantity--compact': density === 'compact' }">
    <label :for="id" class="ui-quantity__label">{{ props.label }}</label>
    <div class="ui-quantity__controls">
      <v-btn
        icon="mdi-minus"
        variant="outlined"
        :aria-label="'Уменьшить ' + label.toLowerCase()"
        :disabled="disabled || readonly || value === null || value <= min"
        @mousedown.prevent
        @click="step(-1)"
      />
      <v-text-field
        ref="input"
        :id="id"
        :model-value="draft"
        :aria-label="label"
        :aria-describedby="error ? id + '-error' : undefined"
        :aria-invalid="!!error || undefined"
        type="number"
        inputmode="numeric"
        :min="min"
        :max="max"
        step="1"
        :density="density"
        :disabled="disabled"
        :readonly="readonly"
        :error="!!error"
        hide-details
        @update:model-value="update"
        @blur="commit"
        @keydown.enter="commit"
      />
      <v-btn
        icon="mdi-plus"
        variant="outlined"
        :aria-label="'Увеличить ' + label.toLowerCase()"
        :disabled="disabled || readonly || value === null || value >= max"
        @mousedown.prevent
        @click="step(1)"
      />
    </div>
    <p v-if="error" :id="id + '-error'" class="ui-quantity__error" role="alert">{{ error }}</p>
  </div>
</template>
<style scoped>
.ui-quantity {
  --quantity-height: max(var(--mf-touch-size), var(--mf-control-height));
  width: calc(var(--quantity-height) * 2 + 4rem + var(--mf-space-2) * 2);
  max-width: 100%;
}
.ui-quantity--compact {
  --quantity-height: max(var(--mf-touch-size), var(--mf-control-compact));
}
.ui-quantity__label {
  display: block;
  font-size: var(--mf-text-small);
  line-height: 1.5;
  margin-bottom: var(--mf-space-1);
  color: rgb(var(--v-theme-on-surface));
}
.ui-quantity__controls {
  display: grid;
  grid-template-columns: var(--quantity-height) minmax(0, 1fr) var(--quantity-height);
  gap: var(--mf-space-2);
  align-items: center;
}
.ui-quantity__controls :deep(.v-input) {
  --v-input-control-height: var(--quantity-height);
}
.ui-quantity__controls :deep(.v-btn.v-btn--icon) {
  width: var(--quantity-height);
  height: var(--quantity-height);
  min-width: var(--quantity-height);
  min-height: var(--quantity-height);
}
.ui-quantity__controls :deep(input) {
  text-align: center;
  padding-inline: var(--mf-space-1);
  -moz-appearance: textfield;
}
.ui-quantity__controls :deep(input::-webkit-inner-spin-button),
.ui-quantity__controls :deep(input::-webkit-outer-spin-button) {
  -webkit-appearance: none;
  margin: 0;
}
.ui-quantity__controls :deep(.v-field__input) {
  padding-inline: var(--mf-space-1);
}
.ui-quantity__error {
  margin-top: var(--mf-space-1);
  color: rgb(var(--v-theme-error));
  font-size: var(--mf-text-small);
  line-height: 1.5;
}
</style>
