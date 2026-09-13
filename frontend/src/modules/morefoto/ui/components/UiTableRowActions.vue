<script setup lang="ts">
import type { UiTableRow } from '../table-types';
withDefaults(defineProps<{ row: UiTableRow; removable?: boolean }>(), { removable: true });
defineEmits<{ open: []; remove: [] }>();
</script>
<template>
  <div class="ui-row-actions">
    <v-btn variant="text" density="compact" :aria-label="'Открыть ' + row.id" :data-open-id="row.id" @click="$emit('open')">Открыть</v-btn>
    <v-menu v-if="removable">
      <template #activator="{ props: menuProps }">
        <v-btn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" :aria-label="'Действия ' + row.id" />
      </template>
      <v-list class="morefoto-app mf-ui-overlay" density="compact">
        <v-list-item :title="'Открыть ' + row.id" @click="$emit('open')" />
        <v-list-item :title="'Удалить ' + row.id" base-color="error" @click="$emit('remove')" />
      </v-list>
    </v-menu>
  </div>
</template>
<style scoped>
.ui-row-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-1);
}
</style>
