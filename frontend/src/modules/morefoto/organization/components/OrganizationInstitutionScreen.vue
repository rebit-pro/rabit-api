<script setup lang="ts">
import { computed, shallowRef, useTemplateRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganization } from '../composables/useOrganization';
import type { UiTableColumn } from '../../ui/table-types';
import OrganizationTable from './OrganizationTable.vue';
import OrganizationEditor from './OrganizationEditor.vue';
import OrganizationLoadState from './OrganizationLoadState.vue';
const route = useRoute();
const router = useRouter();
const { data, loading, error, reload } = useOrganization();
const institution = computed(() => data.value?.institutions.find((item) => item.id === route.params.institutionId));
const curator = computed(() => data.value?.staff.find((item) => item.id === institution.value?.curatorId));
const head = computed(() => data.value?.staff.find((item) => item.id === institution.value?.headId));
const editor = useTemplateRef('editor');
const notice = shallowRef('');
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Съёмка', primary: true, sortable: true },
  { key: 'date', label: 'Дата съёмки', type: 'date', mobile: true, sortable: true },
  { key: 'groups', label: 'Групп', type: 'number', mobile: true, sortable: true }
];
const rows = computed(
  () =>
    data.value?.shoots
      .filter((item) => item.institutionId === institution.value?.id)
      .map((item) => ({
        id: item.id,
        name: item.name,
        date: item.date,
        groups: data.value?.groups.filter((group) => group.shootId === item.id).length ?? 0
      })) ?? []
);
</script>
<template>
  <RouterLink to="/cabinet/institutions" class="mf-back">← Учреждения</RouterLink>
  <OrganizationLoadState v-if="!institution" class="mt-6" :loading="loading" :error="error" :missing="!!data" @retry="reload" />
  <template v-else>
    <header class="mf-page-heading">
      <p class="mf-eyebrow">УЧРЕЖДЕНИЕ</p>
      <h1>{{ institution.name }}</h1>
      <p class="mf-muted">{{ institution.address }}</p>
      <div class="mf-actions mt-5">
        <v-btn prepend-icon="mdi-plus" @click="editor?.open({ kind: 'shoot', parentId: institution.id })">Новая съёмка</v-btn
        ><v-btn variant="outlined" @click="editor?.open({ kind: 'institution', id: institution.id })">Редактировать учреждение</v-btn>
      </div>
    </header>
    <v-alert v-if="notice" role="status" type="success" variant="tonal" class="mb-5">{{ notice }}</v-alert>
    <section class="mf-panel mb-6" aria-label="Назначения учреждения">
      <h2>Ответственные</h2>
      <div class="org-assignees mt-4">
        <p>
          <span class="mf-muted">Куратор</span><br /><strong>{{ curator?.name ?? 'Не назначен' }}</strong
          ><br /><span>{{ curator?.email }}</span>
        </p>
        <p>
          <span class="mf-muted">Руководитель</span><br /><strong>{{ head?.name ?? 'Не назначен' }}</strong
          ><br /><span>{{ head?.email }}</span>
        </p>
      </div>
    </section>
    <OrganizationTable
      title="Съёмки"
      :rows="rows"
      :columns="columns"
      empty="Съёмок пока нет"
      @open="router.push('/cabinet/institutions/' + institution.id + '/shoots/' + $event)"
    />
  </template>
  <OrganizationEditor
    ref="editor"
    :snapshot="data"
    :context="institution?.name"
    @saved="notice = $event.kind === 'shoot' ? 'Съёмка сохранена.' : 'Учреждение сохранено.'"
  />
</template>
<style scoped>
.org-assignees {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
  overflow-wrap: anywhere;
}
@media (max-width: 599px) {
  .org-assignees {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
