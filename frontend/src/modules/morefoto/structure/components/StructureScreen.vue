<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import MfDistribution from '@/components/viz/MfDistribution.vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import { groupStateSegments } from '../../ui/groupStates';
import StructureFields from './StructureFields.vue';
import StructureList from './StructureList.vue';
import ShootTabs from './ShootTabs.vue';
import { useStructurePage } from '../useStructurePage';
import { useStructureEditor } from '../useStructureEditor';
import type { StructureItem, StructureScope } from '../model';
const props = defineProps<{ scope: StructureScope }>();
const auth = useAuthStore();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, error, query, institutionName, reload, page, pages } = useStructurePage(props.scope);
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
const institutionPath = computed(() => '/cabinet/institutions/' + encodeURIComponent(props.scope.institutionId ?? ''));
const crumbs = computed(() =>
  props.scope.kind === 'group'
    ? [
        { title: 'Учреждения', to: '/cabinet/institutions' },
        { title: institutionName.value || 'Учреждение', to: institutionPath.value },
        { title: snapshot.value?.shoot?.name ?? 'Съёмка' }
      ]
    : []
);
const groupStates = computed(() => {
  const counts = snapshot.value?.meta.summary?.byState;
  return counts ? groupStateSegments(counts) : null;
});
// An empty list offers the first record itself; the heading keeps the action only when there is something to add to.
const emptyOffersCreate = computed(() => canManage.value && !!snapshot.value && !snapshot.value.items.length && !query.value.trim());
const emptyTitle = computed(
  () => ({ institution: 'Учреждений пока нет', shoot: 'Съёмок пока нет', group: 'Групп пока нет' })[props.scope.kind]
);
const emptyText = computed(() =>
  query.value.trim()
    ? 'По этому началу названия ничего не найдено.'
    : canManage.value
      ? {
          institution: 'Добавьте первое учреждение, чтобы планировать съёмки.',
          shoot: 'Добавьте съёмку учреждения.',
          group: 'Добавьте группы съёмки: у каждой будут свои фотографии и ссылка для родителей.'
        }[props.scope.kind]
      : 'Организатор ещё не добавил записи или не назначил вам учреждения.'
);
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
  <MfBreadcrumbs v-if="crumbs.length" :items="crumbs" />
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
    <h1>{{ heading }}</h1>
    <p v-if="scope.kind === 'institution'" class="mf-muted">Учреждения и их самостоятельные съёмки</p>
    <p v-else-if="scope.kind === 'shoot'" class="mf-muted">У каждой съёмки свой список групп</p>
    <p v-else-if="snapshot?.shoot" class="mf-muted">
      {{ snapshot.shoot.date ? 'Дата съёмки: ' + snapshot.shoot.date.split('-').reverse().join('.') : 'Дата съёмки не назначена' }}
    </p>
    <div class="mf-actions mt-5">
      <v-btn v-if="canManage && !emptyOffersCreate" prepend-icon="mdi-plus" :disabled="disabled" @click="edit()">{{ createLabel }}</v-btn>
      <v-btn v-if="canManage && scope.kind === 'group'" variant="outlined" :disabled="disabled" @click="editShoot"
        >Редактировать съёмку</v-btn
      >
      <v-btn variant="outlined" :disabled="loading" @click="reload()">Обновить список</v-btn>
    </div>
  </header>
  <ShootTabs v-if="scope.kind === 'group'" :institution-id="scope.institutionId ?? ''" :shoot-id="scope.shootId ?? ''" current="groups" />
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
    <div v-if="groupStates && snapshot.meta.total" class="mf-panel structure-states mb-5">
      <MfDistribution title="Группы по состоянию приёма" :segments="groupStates" :unit-forms="['группы', 'групп', 'групп']" />
    </div>
    <p class="mf-muted mb-4">Всего: {{ snapshot.meta.total }}</p>
    <StructureList
      :items="snapshot.items"
      :scope="scope"
      :disabled="disabled"
      :can-manage="canManage"
      :empty-title="emptyTitle"
      :empty-text="emptyText"
      :create-label="canManage && !query.trim() ? createLabel : ''"
      @edit="edit"
      @create="edit()"
    />
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
