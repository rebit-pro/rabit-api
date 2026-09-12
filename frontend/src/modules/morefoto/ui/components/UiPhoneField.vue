<script setup lang="ts">
import { computed } from 'vue';
import { displayPhone } from '../field-values';
import UiClearButton from './UiClearButton.vue';
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ label?: string; disabled?: boolean; readonly?: boolean; errorMessages?: string }>(), {
  label: 'Телефон'
});
const model = defineModel<string>({ required: true });
const invalid = computed(() => !!props.errorMessages);
function update(value: string | null) {
  model.value = value ?? '';
}
function format() {
  model.value = displayPhone(model.value);
}
</script>
<template>
  <v-text-field
    v-bind="$attrs"
    :model-value="model"
    :label="label"
    :aria-label="label"
    type="tel"
    inputmode="tel"
    autocomplete="tel"
    :disabled="disabled"
    :readonly="readonly"
    :error-messages="errorMessages"
    :aria-invalid="invalid || undefined"
    clearable
    @update:model-value="update"
    @blur="format"
  >
    <template #clear="{ props: clearProps }">
      <UiClearButton v-bind="clearProps" :label="'Очистить ' + label.toLowerCase()" :disabled="disabled || readonly" />
    </template>
  </v-text-field>
</template>
