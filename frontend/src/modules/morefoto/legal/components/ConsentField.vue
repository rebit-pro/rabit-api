<script setup lang="ts">
import { documentPath } from '../rules';
import type { LegalDocument } from '../types';
// The link opens in a new tab, so a filled form is never lost; clicking it does not toggle the checkbox.
defineProps<{ document: LegalDocument; name: string; before: string; link: string; errorMessages?: string; disabled?: boolean }>();
const accepted = defineModel<boolean>({ required: true });
</script>
<template>
  <v-checkbox v-model="accepted" :name="name" color="primary" :disabled="disabled" :error-messages="errorMessages" hide-details="auto">
    <template #label>
      <span
        >{{ before }}
        <a :href="documentPath(document.code, document.version)" target="_blank" rel="noopener" @click.stop>{{ link }}</a></span
      >
    </template>
  </v-checkbox>
</template>
