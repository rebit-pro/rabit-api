<script setup lang="ts">
import { computed, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminDialog from '../../management/components/AdminDialog.vue';
import ConditionsFields from '../../management/components/ConditionsFields.vue';
import CatalogTable from '../../catalog/components/CatalogTable.vue';
import type { Catalog } from '../../commerce/types';
import { structureApi, structureError } from '../../structure/api';
import type { Group, ShootDetail, StructureScope } from '../../structure/model';
import { conditionsApi } from '../api';
import { useConditions } from '../useConditions';
import { useConditionsEditor, type ConditionsEditorSource } from '../useConditionsEditor';
import ConditionsSummary from './ConditionsSummary.vue';
import MfBreadcrumbs from '@/components/navigation/MfBreadcrumbs.vue';
import MfEmptyState from '@/components/states/MfEmptyState.vue';
import ShootTabs from '../../structure/components/ShootTabs.vue';
import { useInstitutionName } from '../../structure/useInstitutionName';

const route = useRoute();
const router = useRouter();
const scope = computed<StructureScope>(() => ({
  kind: 'group',
  institutionId: String(route.params.institutionId),
  shootId: String(route.params.shootId)
}));
const institutionName = useInstitutionName(String(route.params.institutionId));
const shootPath = computed(
  () =>
    '/cabinet/institutions/' +
    encodeURIComponent(scope.value.institutionId ?? '') +
    '/shoots/' +
    encodeURIComponent(scope.value.shootId ?? '')
);
const crumbs = computed(() => [
  { title: 'Учреждения', to: '/cabinet/institutions' },
  { title: institutionName.value || 'Учреждение', to: '/cabinet/institutions/' + encodeURIComponent(scope.value.institutionId ?? '') },
  { title: shoot.value?.name ?? 'Съёмка', to: shootPath.value },
  { title: 'Условия' }
]);
const groups = shallowRef<Group[]>([]);
const shoot = shallowRef<ShootDetail | null>(null);
const groupsLoading = shallowRef(false);
const groupsError = shallowRef('');
let generation = 0;
let alive = true;
async function loadGroups(): Promise<void> {
  const request = ++generation;
  groupsLoading.value = true;
  groupsError.value = '';
  try {
    const currentScope = scope.value;
    const first = await structureApi.list(currentScope, 1, 100);
    const items = [...(first.items as Group[])];
    for (let page = 2; page <= first.meta.totalPages; page++) {
      const next = await structureApi.list(currentScope, page, 100);
      items.push(...(next.items as Group[]));
    }
    if (!alive || request !== generation) return;
    groups.value = items;
    shoot.value = first.shoot ?? null;
  } catch (cause) {
    if (alive && request === generation) {
      groups.value = [];
      shoot.value = null;
      groupsError.value = structureError(cause);
    }
  } finally {
    if (alive && request === generation) groupsLoading.value = false;
  }
}
watch(scope, () => void loadGroups(), { immediate: true });
const groupId = computed<string | null>({
  get: () => {
    const query = typeof route.query.group === 'string' ? route.query.group : null;
    return groups.value.some((group) => group.id === query) ? query : (groups.value[0]?.id ?? null);
  },
  set: (value) => void router.replace({ query: { ...route.query, group: value ?? undefined } })
});
const group = computed(() => groups.value.find((item) => item.id === groupId.value) ?? null);
const globalConditions = useConditions();
const groupConditions = useConditions(groupId);
const notice = shallowRef('');
const catalog = computed<Catalog>(() => ({
  products: globalConditions.snapshot.value?.products ?? [],
  giftThreshold: globalConditions.snapshot.value?.giftThreshold ?? 0,
  giftForStaff: globalConditions.snapshot.value?.giftForStaff ?? false,
  revision: globalConditions.snapshot.value?.catalogRevision ?? 1,
  conditionsRevision: globalConditions.snapshot.value?.revision
}));
async function loadSource(): Promise<ConditionsEditorSource | null> {
  const id = groupId.value;
  if (!id) return null;
  return {
    snapshot: await conditionsApi.get(id),
    groupId: id,
    institutionId: scope.value.institutionId,
    shootId: scope.value.shootId
  };
}
const editor = useConditionsEditor(loadSource, async () => {
  notice.value = 'Условия группы сохранены.';
  await Promise.all([globalConditions.reload(), groupConditions.reload()]);
});
const { command, pending, busy, error: saveError, errors, restored, open, close, reset, save } = editor;
function edit(): void {
  if (groupConditions.snapshot.value && group.value && !groupConditions.loading.value && !groupConditions.error.value) {
    notice.value = '';
    open({
      snapshot: groupConditions.snapshot.value,
      groupId: group.value.id,
      institutionId: scope.value.institutionId,
      shootId: scope.value.shootId
    });
  }
}
async function reloadConditions(): Promise<void> {
  await Promise.all([groupConditions.reload(), globalConditions.reload()]);
}
watch(groupId, () => {
  close();
  notice.value = '';
});
onScopeDispose(() => {
  alive = false;
  generation++;
});
</script>
<template>
  <MfBreadcrumbs :items="crumbs" />
  <header class="mf-page-heading">
    <p class="mf-eyebrow">ПРАВИЛА ПРОДАЖ</p>
    <h1>Условия групп</h1>
    <p class="mf-muted">Собственный прайс или наследование общих условий для каждой группы съёмки</p>
  </header>
  <ShootTabs :institution-id="scope.institutionId ?? ''" :shoot-id="scope.shootId ?? ''" current="conditions" />
  <v-progress-linear v-if="groupsLoading" indeterminate aria-label="Загрузка групп" class="mb-5" />
  <v-alert v-if="groupsError" type="error" variant="tonal" role="alert" class="mb-5"
    >{{ groupsError }}<v-btn variant="text" @click="loadGroups">Повторить</v-btn></v-alert
  >
  <template v-if="!groupsError && !groupsLoading">
    <MfEmptyState
      v-if="!groups.length"
      class="mf-panel"
      title="В съёмке пока нет групп"
      text="Условия настраиваются для групп: сначала добавьте группу на странице съёмки."
      icon="mdi-tag-outline"
    >
      <v-btn :to="shootPath" variant="outlined">Открыть съёмку</v-btn>
    </MfEmptyState>
    <template v-else>
      <v-select
        v-model="groupId"
        :items="groups"
        item-title="name"
        item-value="id"
        label="Группа для настройки условий"
        data-testid="conditions-group"
        class="conditions-group mb-5"
      />
      <v-progress-linear
        v-if="groupConditions.loading.value || globalConditions.loading.value"
        indeterminate
        aria-label="Загрузка условий группы"
        class="mb-5"
      />
      <v-alert v-if="groupConditions.error.value || globalConditions.error.value" type="error" variant="tonal" role="alert" class="mb-5">
        {{ groupConditions.error.value || globalConditions.error.value }}
        <v-btn variant="text" @click="reloadConditions">Повторить</v-btn>
      </v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
      <template v-if="groupConditions.snapshot.value && globalConditions.snapshot.value && !groupConditions.error.value">
        <div class="mf-actions mb-5">
          <v-btn @click="edit">Изменить условия группы</v-btn>
          <span class="mf-muted">{{
            groupConditions.snapshot.value.inherit ? 'Наследует общие условия.' : 'Использует собственные условия.'
          }}</span>
        </div>
        <ConditionsSummary :snapshot="groupConditions.snapshot.value" class="mb-6" />
        <CatalogTable :products="groupConditions.snapshot.value.products" :disabled="false" :editable="false" />
      </template>
    </template>
  </template>
  <AdminDialog
    :open="!!command"
    :title="'Условия · ' + (group?.name ?? '')"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    :can-reset="!pending"
    :fields-disabled="!!pending"
    @close="close"
    @save="save"
    @reset="reset"
  >
    <ConditionsFields
      v-if="command"
      v-model="command"
      :errors="errors"
      :catalog="catalog"
      :payment-costs="groupConditions.snapshot.value?.paymentCosts ?? null"
    />
  </AdminDialog>
</template>
<style scoped>
.conditions-group {
  max-width: 560px;
}
</style>
