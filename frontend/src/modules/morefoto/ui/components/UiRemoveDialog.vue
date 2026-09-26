<script setup lang="ts">
import { computed, useId } from 'vue';
/** Confirmation of a removal from a table: what goes away, what goes with it, and one irreversible button. */
const props = defineProps<{ open: boolean; title: string; names: string[]; busy: boolean; testid?: string }>();
const emit = defineEmits<{ confirm: []; close: [] }>();
const titleId = useId();
const show = computed({
  get: () => props.open,
  set: (open: boolean) => {
    if (!open && !props.busy) emit('close');
  }
});
</script>
<template>
  <v-dialog v-model="show" max-width="560" :aria-labelledby="titleId">
    <v-card class="morefoto-app mf-panel ui-remove-dialog" :data-testid="testid">
      <h2 :id="titleId">{{ title }}</h2>
      <ul class="ui-remove-names">
        <li v-for="name in names.slice(0, 10)" :key="name">{{ name }}</li>
        <li v-if="names.length > 10">и ещё {{ names.length - 10 }}</li>
      </ul>
      <p><slot /></p>
      <slot name="warning" />
      <div class="mf-actions">
        <v-btn color="error" :loading="busy" @click="emit('confirm')">Удалить</v-btn>
        <v-btn variant="outlined" :disabled="busy" @click="show = false">Отмена</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.ui-remove-dialog {
  display: grid;
  gap: 16px;
  padding: 24px;
}
.ui-remove-dialog p {
  line-height: 1.6;
}
.ui-remove-names {
  display: grid;
  gap: 4px;
  margin-left: var(--mf-space-5);
  overflow-wrap: anywhere;
}
@media (max-width: 760px) {
  .ui-remove-dialog {
    padding: 16px;
  }
}
</style>
