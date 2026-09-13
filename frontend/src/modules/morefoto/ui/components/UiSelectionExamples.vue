<script setup lang="ts">
import { computed, ref } from 'vue';
import type { UiFieldState } from '../types';
const props = defineProps<{ state: UiFieldState; longText: boolean }>();
const accepted = ref(false);
const channel = ref('email');
const notifications = ref(true);
const disabled = computed(() => props.state === 'disabled' || props.state === 'loading');
const readonly = computed(() => props.state === 'readonly');
const error = computed(() => (props.state === 'error' ? 'Подтвердите выбор.' : ''));
</script>
<template>
  <section class="mf-panel ui-example-section" aria-labelledby="ui-selection-title">
    <div>
      <h2 id="ui-selection-title">Выбор и переключатели</h2>
      <p class="mf-muted">Подписи переносятся, зона нажатия остаётся удобной.</p>
    </div>
    <v-checkbox
      v-model="accepted"
      :label="longText ? 'Состав заказа, условия получения и выбранный способ связи проверены' : 'Состав заказа проверен'"
      :disabled="disabled"
      :readonly="readonly"
      :error-messages="error"
    />
    <v-radio-group v-model="channel" label="Способ получения чека" :disabled="disabled" :readonly="readonly" :error-messages="error">
      <v-radio label="На email" value="email" /><v-radio label="В MAX — пока недоступно" value="max" disabled />
    </v-radio-group>
    <v-switch
      v-model="notifications"
      label="Сообщать об изменении заказа"
      :disabled="disabled"
      :readonly="readonly"
      :error-messages="error"
    />
  </section>
</template>
