<script setup lang="ts">
/** Search bar above a structure table: the search also starts by itself after typing (useStructurePage). */
defineProps<{ label: string; accessibleName: string; loading: boolean }>();
const emit = defineEmits<{ search: []; refresh: [] }>();
const query = defineModel<string>({ required: true });
</script>
<template>
  <form class="mf-panel structure-search mf-filters" role="search" @submit.prevent="emit('search')">
    <v-text-field
      :model-value="query"
      :label="label"
      :aria-label="accessibleName"
      prepend-inner-icon="mdi-magnify"
      maxlength="100"
      density="compact"
      hide-details
      clearable
      @update:model-value="query = $event ?? ''"
      @click:clear="query = ''"
    />
    <v-btn type="submit" color="primary" :disabled="loading">Найти</v-btn>
    <v-btn variant="text" prepend-icon="mdi-refresh" :disabled="loading" @click="emit('refresh')">Обновить</v-btn>
  </form>
</template>
<style scoped>
.structure-search {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-3);
  margin-bottom: var(--mf-space-4);
  padding: var(--mf-space-3);
}
.structure-search .v-input {
  min-width: 220px;
  flex: 1;
}
</style>
