<script setup lang="ts">
import { computed, shallowRef, useTemplateRef } from 'vue';
import { useRouter } from 'vue-router';
import { useOrganization } from '../composables/useOrganization';
import type { UiTableColumn } from '../../ui/table-types';
import OrganizationTable from './OrganizationTable.vue';
import OrganizationEditor from './OrganizationEditor.vue';
import OrganizationLoadState from './OrganizationLoadState.vue';
const { data, loading, error, reload } = useOrganization();
const router = useRouter();
const editor = useTemplateRef('editor');
const notice = shallowRef('');
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Учреждение', primary: true, sortable: true },
  { key: 'address', label: 'Адрес', mobile: true },
  { key: 'curator', label: 'Куратор', mobile: true, sortable: true },
  { key: 'shoots', label: 'Съёмок', type: 'number', mobile: true, sortable: true }
];
const rows = computed(
  () =>
    data.value?.institutions.map((item) => ({
      id: item.id,
      name: item.name,
      address: item.address,
      curator: data.value?.staff.find((person) => person.id === item.curatorId)?.name ?? 'Не назначен',
      shoots: data.value?.shoots.filter((shoot) => shoot.institutionId === item.id).length ?? 0
    })) ?? []
);
</script>
<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАТОР</p>
    <h1>Учреждения</h1>
    <p class="mf-muted">Съёмки, группы и ответственные в одном месте.</p>
    <div class="mf-actions mt-5">
      <v-btn prepend-icon="mdi-plus" :disabled="!data" @click="editor?.open({ kind: 'institution' })">Новое учреждение</v-btn>
    </div>
  </header>
  <v-alert v-if="notice" role="status" type="success" variant="tonal" class="mb-5">{{ notice }}</v-alert>
  <OrganizationLoadState v-if="!data" :loading="loading" :error="error" @retry="reload" />
  <OrganizationTable
    v-else
    title="Учреждения"
    :rows="rows"
    :columns="columns"
    empty="Учреждений пока нет"
    @open="router.push('/cabinet/institutions/' + $event)"
  />
  <OrganizationEditor ref="editor" :snapshot="data" @saved="notice = 'Учреждение сохранено.'" />
</template>
