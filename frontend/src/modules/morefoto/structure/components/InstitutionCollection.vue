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
  /** Groups only: a group is created inside a shoot, so without shoots there is nothing to add it to. */
  hasShoots?: boolean;
}>();
const emit = defineEmits<{
  page: [page: number];
  edit: [item: StructureItem];
  create: [];
}>();
const title = computed(() => (props.kind === 'shoot' ? 'Съёмки учреждения' : 'Группы учреждения'));
const pages = computed(() => Math.max(1, props.meta.totalPages));
const emptyText = computed(() =>
  props.kind === 'shoot'
    ? props.canManage
      ? 'Добавьте съёмку — затем в ней появятся группы.'
      : 'Организатор ещё не запланировал съёмки этого учреждения.'
    : !props.canManage
      ? 'В съёмках учреждения пока нет групп.'
      : props.hasShoots
        ? 'Добавьте группу: у каждой будут свои фотографии и ссылка для родителей.'
        : 'Сначала добавьте съёмку — группа создаётся внутри неё.'
);
const createLabel = computed(() => (props.kind === 'shoot' ? 'Новая съёмка' : 'Новая группа'));
const canCreate = computed(() => props.canManage && (props.kind === 'shoot' || props.hasShoots));
</script>
<template>
  <section class="institution-collection" :aria-label="title" :data-testid="kind === 'shoot' ? 'institution-shoots' : 'institution-groups'">
    <div class="institution-collection-heading">
      <div>
        <h2>{{ title }}</h2>
        <p class="mf-muted mt-2">Всего: {{ meta.total }}</p>
      </div>
      <v-btn v-if="canCreate && items.length" prepend-icon="mdi-plus" :disabled="disabled" @click="emit('create')">
        {{ createLabel }}
      </v-btn>
    </div>
    <StructureList
      :items="items"
      :scope="{ kind, institutionId }"
      :disabled="disabled"
      :can-manage="canManage"
      :link-groups-to-shoots="canManage && kind === 'group'"
      :empty-title="kind === 'shoot' ? 'Съёмок пока нет' : 'Групп пока нет'"
      :empty-text="emptyText"
      :create-label="canCreate ? createLabel : ''"
      @edit="emit('edit', $event)"
      @create="emit('create')"
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
  margin-top: var(--mf-space-8);
}
.institution-collection-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--mf-space-5);
  margin-bottom: var(--mf-space-5);
}
@media (max-width: 600px) {
  .institution-collection-heading {
    flex-direction: column;
  }
}
</style>
