<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import AdminDialog from '../../management/components/AdminDialog.vue';
import StructureFields from './StructureFields.vue';
import StructureList from './StructureList.vue';
import { useStructurePage } from '../useStructurePage';
import { useStructureEditor } from '../useStructureEditor';
import type { StructureItem, StructureScope } from '../model';
const props = defineProps<{ scope: StructureScope }>();
const auth = useAuthStore();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, error, query, reload, page, pages } = useStructurePage(props.scope);
const notice = shallowRef('');
const editor = useStructureEditor(props.scope, async () => {
  notice.value = 'Изменения сохранены.';
  await reload();
});
const { draft, busy, restored, error: saveError, errors, close, refresh, save } = editor;
const heading = computed(() =>
  props.scope.kind === 'institution'
    ? 'Учреждения'
    : props.scope.kind === 'shoot'
      ? 'Съёмки учреждения'
      : (snapshot.value?.shoot?.name ?? 'Съёмка')
);
const createLabel = computed(
  () =>
    ({
      institution: 'Новое учреждение',
      shoot: 'Новая съёмка',
      group: 'Новая группа'
    })[props.scope.kind]
);
const title = computed(() =>
  !draft.value
    ? ''
    : (draft.value.id
        ? {
            institution: 'Редактирование учреждения',
            shoot: 'Редактирование съёмки',
            group: 'Редактирование группы'
          }
        : {
            institution: 'Новое учреждение',
            shoot: 'Новая съёмка',
            group: 'Новая группа'
          })[draft.value.kind]
);
const disabled = computed(() => loading.value || !snapshot.value || !!error.value);
function edit(item?: StructureItem): void {
  if (canManage.value && !disabled.value) {
    notice.value = '';
    editor.open(props.scope.kind, item);
  }
}
function editShoot(): void {
  if (canManage.value && snapshot.value?.shoot && !disabled.value) editor.open('shoot', snapshot.value.shoot);
}
</script>
<template>
  <nav v-if="scope.kind !== 'institution'" class="mf-actions mb-5" aria-label="Навигация по структуре">
    <RouterLink to="/cabinet/institutions">Учреждения</RouterLink>
    <RouterLink v-if="scope.kind === 'group'" :to="'/cabinet/institutions/' + encodeURIComponent(scope.institutionId ?? '')"
      >Съёмки учреждения</RouterLink
    >
  </nav>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
    <h1>{{ heading }}</h1>
    <p v-if="scope.kind === 'institution'" class="mf-muted">Учреждения и их самостоятельные съёмки</p>
    <p v-else-if="scope.kind === 'shoot'" class="mf-muted">У каждой съёмки свой список групп</p>
    <p v-else-if="snapshot?.shoot" class="mf-muted">
      {{ snapshot.shoot.date ? 'Дата съёмки: ' + snapshot.shoot.date.split('-').reverse().join('.') : 'Дата съёмки не назначена' }}
    </p>
    <div class="mf-actions mt-5">
      <v-btn v-if="canManage" prepend-icon="mdi-plus" :disabled="disabled" @click="edit()">{{ createLabel }}</v-btn>
      <v-btn v-if="canManage && scope.kind === 'group'" variant="outlined" :disabled="disabled" @click="editShoot"
        >Редактировать съёмку</v-btn
      >
      <v-btn
        v-if="canManage && scope.kind === 'group'"
        variant="outlined"
        :disabled="disabled"
        :to="
          '/cabinet/institutions/' +
          encodeURIComponent(scope.institutionId ?? '') +
          '/shoots/' +
          encodeURIComponent(scope.shootId ?? '') +
          '/photos'
        "
        >Фотографии</v-btn
      >
      <v-btn variant="outlined" :disabled="loading" @click="reload()">Обновить список</v-btn>
    </div>
  </header>
  <form v-if="scope.kind === 'institution'" class="structure-search mf-actions mb-5" @submit.prevent="reload(1)">
    <v-text-field
      :model-value="query"
      @update:model-value="query = $event ?? ''"
      label="Поиск по началу названия"
      aria-label="Поиск учреждений"
      maxlength="100"
      hide-details
      clearable
      @click:clear="query = ''"
    />
    <v-btn type="submit" variant="outlined" :disabled="loading">Найти</v-btn>
  </form>
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка структуры" class="mb-5" />
  <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <section v-if="snapshot && !error" aria-label="Записи структуры">
    <h2 v-if="scope.kind === 'group'" class="mb-4">Группы</h2>
    <p class="mf-muted mb-4">Всего: {{ snapshot.meta.total }}</p>
    <StructureList :items="snapshot.items" :scope="scope" :disabled="disabled" :can-manage="canManage" @edit="edit" />
    <nav v-if="pages > 1" class="mf-actions mt-5" aria-label="Страницы структуры">
      <v-btn variant="outlined" :disabled="loading || page === 1" @click="reload(page - 1)">Предыдущая</v-btn>
      <span role="status">Страница {{ page }} из {{ pages }}</span>
      <v-btn variant="outlined" :disabled="loading || page === pages" @click="reload(page + 1)">Следующая</v-btn>
    </nav>
  </section>
  <AdminDialog
    :open="!!draft"
    :title="title"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    :can-reset="!draft?.pending"
    :fields-disabled="!!draft?.pending"
    @close="close"
    @save="save"
    @reset="refresh"
  >
    <StructureFields v-if="draft" v-model="draft.fields" :kind="draft.kind" :existing="!!draft.id" :errors="errors" />
  </AdminDialog>
</template>
<style scoped>
.structure-search .v-input {
  min-width: 220px;
  flex: 1;
}
.structure-search {
  align-items: center;
}
</style>
