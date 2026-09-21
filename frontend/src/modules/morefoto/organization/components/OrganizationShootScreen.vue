<script setup lang="ts">
import { computed, shallowRef, useTemplateRef } from 'vue';
import { useRoute } from 'vue-router';
import { useOrganization } from '../composables/useOrganization';
import type { UiTableColumn } from '../../ui/table-types';
import OrganizationTable from './OrganizationTable.vue';
import OrganizationEditor from './OrganizationEditor.vue';
import OrganizationLoadState from './OrganizationLoadState.vue';
const route = useRoute();
const { data, loading, error, reload } = useOrganization();
const institution = computed(() => data.value?.institutions.find((item) => item.id === route.params.institutionId));
const shoot = computed(() =>
  data.value?.shoots.find((item) => item.id === route.params.shootId && item.institutionId === institution.value?.id)
);
const date = computed(() =>
  shoot.value?.date
    ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'long' }).format(new Date(shoot.value.date + 'T12:00:00Z'))
    : 'Дата съёмки не указана'
);
const editor = useTemplateRef('editor');
const notice = shallowRef('');
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Группа', primary: true, sortable: true },
  { key: 'kind', label: 'Тип', mobile: true },
  { key: 'teacher', label: 'Ответственный', mobile: true, sortable: true },
  {
    key: 'state',
    label: 'Приём заказов',
    type: 'status',
    mobile: true,
    statuses: {
      preparing: { label: 'Подготовка', tone: 'neutral' },
      open: { label: 'Приём открыт', tone: 'success' },
      closed: { label: 'Приём закрыт', tone: 'warning' }
    }
  }
];
const rows = computed(
  () =>
    data.value?.groups
      .filter((item) => item.shootId === shoot.value?.id)
      .map((item) => ({
        id: item.id,
        name: item.name,
        kind: item.kind === 'staff' ? 'Сотрудники' : 'Группа / класс',
        teacher: data.value?.staff.find((person) => person.id === item.teacherId)?.name ?? 'Не назначен',
        state: item.state,
        galleryToken: item.galleryToken
      })) ?? []
);
</script>
<template>
  <RouterLink :to="institution ? '/cabinet/institutions/' + institution.id : '/cabinet/institutions'" class="mf-back"
    >← {{ institution?.name ?? 'Учреждения' }}</RouterLink
  >
  <OrganizationLoadState v-if="!shoot || !institution" class="mt-6" :loading="loading" :error="error" :missing="!!data" @retry="reload" />
  <template v-else>
    <header class="mf-page-heading">
      <p class="mf-eyebrow">СЪЁМКА</p>
      <h1>{{ shoot.name }}</h1>
      <p class="mf-muted">{{ date }}</p>
      <div class="mf-actions mt-5">
        <v-btn variant="outlined" :to="'/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos'">Фотографии</v-btn>
        <v-btn variant="outlined" :to="'/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/conditions'"
          >Условия групп</v-btn
        >
        <v-btn variant="outlined" :to="{ path: '/cabinet/links', query: { shoot: shoot.id } }">Ссылки и сроки</v-btn>
        <v-btn variant="outlined" :to="{ path: '/cabinet/staff-requests', query: { shoot: shoot.id } }">Заявки на списки сотрудников</v-btn>
        <v-btn prepend-icon="mdi-plus" @click="editor?.open({ kind: 'group', parentId: shoot.id })">Новая группа</v-btn
        ><v-btn variant="outlined" @click="editor?.open({ kind: 'shoot', id: shoot.id, parentId: institution.id })"
          >Редактировать съёмку</v-btn
        >
      </div>
    </header>
    <v-alert v-if="notice" role="status" type="success" variant="tonal" class="mb-5">{{ notice }}</v-alert>
    <p class="mf-muted mb-5">
      Группы этой съёмки имеют отдельные ссылки. Подготовка группы и просмотр галереи не запускают срок приёма заказов.
    </p>
    <OrganizationTable
      title="Группы"
      :rows="rows"
      :columns="columns"
      empty="Групп пока нет"
      action="Редактировать"
      @open="editor?.open({ kind: 'group', id: $event, parentId: shoot.id })"
    />
  </template>
  <OrganizationEditor
    ref="editor"
    :snapshot="data"
    :context="[institution?.name, shoot?.name].filter(Boolean).join(' → ')"
    @saved="notice = $event.kind === 'group' ? 'Группа сохранена.' : 'Съёмка сохранена.'"
  />
</template>
