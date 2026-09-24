<script setup lang="ts">
import { shallowRef } from 'vue';

const password = defineModel<string>('password', { required: true });
const confirmation = defineModel<string>('confirmation', { required: true });
defineProps<{ disabled?: boolean; label?: string }>();
const visible = shallowRef(false);
const rules = [(value: string) => value.length >= 10 || 'Не меньше 10 символов'];
const confirmRules = [(value: string) => value === password.value || 'Пароли не совпадают'];
</script>

<template>
  <v-text-field
    v-model="password"
    :label="label ?? 'Новый пароль'"
    :aria-label="label ?? 'Новый пароль'"
    :type="visible ? 'text' : 'password'"
    autocomplete="new-password"
    :rules="rules"
    :disabled="disabled"
    hint="Не меньше 10 символов, не совпадает с email"
    persistent-hint
    required
  >
    <template #append-inner>
      <v-btn
        type="button"
        :icon="visible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'"
        :aria-pressed="visible"
        variant="text"
        color="secondary"
        :disabled="disabled"
        @click="visible = !visible"
      />
    </template>
  </v-text-field>
  <v-text-field
    v-model="confirmation"
    label="Повторите пароль"
    aria-label="Повторите пароль"
    :type="visible ? 'text' : 'password'"
    autocomplete="new-password"
    :rules="confirmRules"
    :disabled="disabled"
    required
  />
</template>
