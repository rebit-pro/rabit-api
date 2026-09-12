<script setup lang="ts">
defineOptions({ inheritAttrs: false });
defineProps<{ label: string; disabled?: boolean }>();
</script>
<template>
  <button v-bind="$attrs" type="button" tabindex="0" :aria-label="label" :disabled="disabled" class="mf-clear-button">
    <v-icon icon="mdi-close" aria-hidden="true" />
  </button>
</template>
<style scoped>
.mf-clear-button {
  display: grid;
  place-items: center;
  width: var(--mf-touch-size);
  height: min(var(--mf-touch-size), var(--v-input-control-height));
  flex: 0 0 auto;
  border-radius: var(--mf-radius-field);
  color: rgb(var(--v-theme-on-surface));
}
.mf-clear-button .v-icon {
  font-size: var(--mf-icon-size);
}
.mf-clear-button:disabled {
  opacity: 0.4;
}
</style>
