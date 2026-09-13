<script setup lang="ts">
import { computed } from 'vue';
import StructureList from './StructureList.vue';
import type { PageMeta, StructureItem } from '../model';
const props = defineProps<{
  institutionId: string;
  kind: 'shoot' | 'group';
  items: StructureItem[];
  meta: PageMeta;
  disabled: boolean;
  canManage: boolean;
}>();
const emit = defineEmits<{ page: [page: number]; edit: [item: StructureItem]; create: [] }>();
const title = computed(() => (props.kind === 'shoot' ? 'Съёмки учреждения' : 'Группы учреждения'));
const pages = computed(() => Math.max(1, props.meta.totalPages));
</script>
<template>
  <section class="institution-collection" :aria-label="title" :data-testid="kind === 'shoot' ? 'institution-shoots' : 'institution-groups'">
    <div class="institution-collection-heading">
      <div>
        <h2>{{ title }}</h2>
        <p class="mf-muted mt-2">Всего: {{ meta.total }}</p>
      </div>
      <v-btn v-if="kind === 'shoot' && canManage" prepend-icon="mdi-plus" :disabled="disabled" @click="emit('create')">
        Новая съёмка
      </v-btn>
    </div>
    <p v-if="kind === 'group' && canManage" class="mf-muted mb-4">Чтобы добавить или изменить группу, откройте её съёмку.</p>
    <StructureList
      :items="items"
      :scope="{ kind, institutionId }"
      :disabled="disabled"
      :can-manage="canManage && kind === 'shoot'"
      :link-groups-to-shoots="canManage && kind === 'group'"
      @edit="emit('edit', $event)"
    />
    <nav v-if="pages > 1" class="mf-actions mt-5" :aria-label="'Страницы: ' + title.toLowerCase()">
      <v-btn variant="outlined" :disabled="disabled || meta.page === 1" @click="emit('page', meta.page - 1)">Предыдущая</v-btn>
      <span role="status">Страница {{ meta.page }} из {{ pages }}</span>
      <v-btn variant="outlined" :disabled="disabled || meta.page >= pages" @click="emit('page', meta.page + 1)">Следующая</v-btn>
    </nav>
  </section>
</template>
<style scoped>
.institution-collection {
  margin-top: 36px;
}
.institution-collection-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 20px;
}
@media (max-width: 600px) {
  .institution-collection-heading {
    flex-direction: column;
  }
}
</style>
