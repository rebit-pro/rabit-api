<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import InstitutionOverview from './InstitutionOverview.vue';
import InstitutionCollection from './InstitutionCollection.vue';
import StructureFields from './StructureFields.vue';
import { useInstitutionPage } from '../useInstitutionPage';
import { useStructureEditor } from '../useStructureEditor';
import type { StructureItem } from '../model';
const props = defineProps<{ institutionId: string }>();
const auth = useAuthStore();
const canManage = computed(() => auth.user?.role === 'organizer' && !!auth.user.permissions?.includes('organization.manage'));
const { snapshot, loading, error, reload } = useInstitutionPage(props.institutionId);
const notice = shallowRef('');
const editor = useStructureEditor({ kind: 'shoot', institutionId: props.institutionId }, async () => {
  notice.value = 'Изменения сохранены.';
  await reload();
});
const { draft, busy, restored, error: saveError, errors, close, refresh, save } = editor;
const disabled = computed(() => loading.value || !snapshot.value || !!error.value);
const title = computed(() =>
  !draft.value
    ? ''
    : draft.value.kind === 'institution'
      ? 'Редактирование учреждения'
      : draft.value.id
        ? 'Редактирование съёмки'
        : 'Новая съёмка'
);
function editShoot(item?: StructureItem): void {
  if (canManage.value && !disabled.value) {
    notice.value = '';
    editor.open('shoot', item);
  }
}
function editInstitution(): void {
  if (canManage.value && !disabled.value && snapshot.value) {
    notice.value = '';
    editor.open('institution', snapshot.value);
  }
}
</script>
<template>
  <MfBreadcrumbs :items="[{ title: 'Учреждения', to: '/cabinet/institutions' }, { title: snapshot?.name ?? 'Учреждение' }]" />
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ОРГАНИЗАЦИЯ СЪЁМОК</p>
    <h1 class="institution-title">{{ snapshot?.name ?? 'Учреждение' }}</h1>
    <p class="mf-muted">Реквизиты, назначения, съёмки и группы учреждения</p>
    <div class="mf-actions mt-5">
      <v-btn v-if="canManage" variant="outlined" :disabled="disabled" @click="editInstitution">Редактировать учреждение</v-btn>
      <v-btn variant="outlined" :disabled="loading" @click="reload()">Обновить список</v-btn>
    </div>
  </header>
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка учреждения" class="mb-5" />
  <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
  <v-alert v-if="notice && !error" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <template v-if="snapshot && !error">
    <InstitutionOverview :institution="snapshot" />
    <InstitutionCollection
      :institution-id="institutionId"
      kind="shoot"
      :items="snapshot.shoots.items"
      :meta="snapshot.shoots.meta"
      :disabled="disabled"
      :can-manage="canManage"
      @create="editShoot()"
      @edit="editShoot"
      @page="reload({ shootsPage: $event })"
    />
    <InstitutionCollection
      :institution-id="institutionId"
      kind="group"
      :items="snapshot.groups.items"
      :meta="snapshot.groups.meta"
      :disabled="disabled"
      :can-manage="canManage"
      @page="reload({ groupsPage: $event })"
    />
  </template>
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
.institution-title {
  overflow-wrap: anywhere;
}
</style>
