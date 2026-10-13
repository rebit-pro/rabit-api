<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import StructureFields from './StructureFields.vue';
import InstitutionTable from './InstitutionTable.vue';
import GroupTable from './GroupTable.vue';
import StructureSearch from './StructureSearch.vue';
import StructureRemoveDialog from './StructureRemoveDialog.vue';
import ShootTabs from './ShootTabs.vue';
import { useStructurePage } from '../useStructurePage';
import { useStructureEditor } from '../useStructureEditor';
import { structureApi, structureRemovalError } from '../api';
import type { Group, Institution, StructureItem, StructureScope } from '../model';
const props = defineProps<{ scope: StructureScope }>();
const auth = useAuthStore();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, error, query, institutionName, reload, page, pageSize, resize } = useStructurePage(props.scope);
// Countdowns of the group table count from the page's own clock: the structure answer has no server moment.
const now = new Date().toISOString();
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
const institutions = computed(() => (snapshot.value?.items ?? []) as Institution[]);
const groups = computed(() => (snapshot.value?.items ?? []) as Group[]);
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
// The institution list removes one institution at a time (#92 DEC-05); shoots and groups are removed on the institution page.
const removable = computed(() => canManage.value && props.scope.kind === 'institution');
const removal = shallowRef<StructureItem | null>(null);
const removing = shallowRef(false);
const removeError = shallowRef('');
function edit(item?: StructureItem): void {
  if (canManage.value && !disabled.value) {
    notice.value = '';
    removeError.value = '';
    editor.open(props.scope.kind, item);
  }
}
function askRemove(item: StructureItem): void {
  if (!removable.value || disabled.value) return;
  notice.value = '';
  removeError.value = '';
  removal.value = item;
}
async function confirmRemove(): Promise<void> {
  const item = removal.value;
  if (!item) return;
  removing.value = true;
  try {
    await structureApi.remove('institution', item.id);
    notice.value = 'Учреждение «' + item.name + '» удалено.';
    await reload();
  } catch (cause) {
    removeError.value = item.name + ': ' + structureRemovalError(cause);
  } finally {
    removing.value = false;
    removal.value = null;
  }
}
function editShoot(): void {
  if (canManage.value && snapshot.value?.shoot && !disabled.value) editor.open('shoot', snapshot.value.shoot);
}
</script>
<template>
  <MfBreadcrumbs v-if="crumbs.length" :items="crumbs" />
  <header class="mf-page-heading structure-heading">
    <div>
      <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
      <h1>{{ heading }}</h1>
      <p v-if="scope.kind === 'institution'" class="mf-muted">Учреждения и их самостоятельные съёмки</p>
      <p v-else-if="scope.kind === 'shoot'" class="mf-muted">У каждой съёмки свой список групп</p>
      <p v-else-if="snapshot?.shoot" class="mf-muted">
        {{ snapshot.shoot.date ? 'Дата съёмки: ' + snapshot.shoot.date.split('-').reverse().join('.') : 'Дата съёмки не назначена' }}
      </p>
    </div>
    <div v-if="canManage" class="mf-actions">
      <v-btn v-if="scope.kind === 'group'" variant="outlined" :disabled="disabled" @click="editShoot">Редактировать съёмку</v-btn>
      <v-btn prepend-icon="mdi-plus" :disabled="disabled" @click="edit()">{{ createLabel }}</v-btn>
    </div>
  </header>
  <ShootTabs v-if="scope.kind === 'group'" :institution-id="scope.institutionId ?? ''" :shoot-id="scope.shootId ?? ''" current="groups" />
  <StructureSearch
    v-if="scope.kind === 'institution'"
    v-model="query"
    label="Поиск по началу названия"
    accessible-name="Поиск учреждений"
    :loading="loading"
    @search="reload(1)"
    @refresh="reload()"
  />
  <v-alert v-if="removeError" type="error" variant="tonal" role="alert" class="mb-5">{{ removeError }}</v-alert>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <InstitutionTable
    v-if="scope.kind === 'institution'"
    :institutions="institutions"
    :total="snapshot?.meta.total ?? 0"
    :page="page"
    :page-size="pageSize"
    :loading="loading && !snapshot"
    :error="error"
    :can-manage="canManage"
    :disabled="disabled"
    :empty-description="emptyText"
    @page="reload($event)"
    @page-size="resize"
    @edit="edit"
    @remove="askRemove"
    @retry="reload()"
  />
  <GroupTable
    v-else
    :groups="groups"
    :total="snapshot?.meta.total ?? 0"
    :page="page"
    :page-size="pageSize"
    :loading="loading && !snapshot"
    :error="error"
    :can-manage="canManage"
    :disabled="disabled"
    :now="now"
    :empty-description="emptyText"
    @page="reload($event)"
    @page-size="resize"
    @edit="edit"
    @retry="reload()"
  />
  <StructureRemoveDialog
    :kind="removal ? 'institution' : null"
    :names="removal ? [removal.name] : []"
    :busy="removing"
    @confirm="confirmRemove"
    @close="removal = null"
  />
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
.structure-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--mf-space-4);
}
</style>
