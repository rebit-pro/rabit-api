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
        <a class="consent-link" :href="documentPath(document.code, document.version)" target="_blank" rel="noopener" @click.stop>{{
          link
        }}</a></span
      >
    </template>
  </v-checkbox>
</template>
<style scoped>
/* #157: the document link keeps the product's link colour, not the browser default blue. */
.consent-link {
  color: var(--mf-color-link);
  /* FB-4: document links read like the other links of the page — coloured, underlined only on hover and focus. */
  text-decoration: none;
}
.consent-link:hover,
.consent-link:focus-visible {
  text-decoration: underline;
}
</style>
