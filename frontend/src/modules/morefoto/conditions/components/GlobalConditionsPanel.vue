<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import ConditionsFields from '../../management/components/ConditionsFields.vue';
import type { Catalog } from '../../commerce/types';
import { conditionsApi } from '../api';
import { useConditions } from '../useConditions';
import { useConditionsEditor, type ConditionsEditorSource } from '../useConditionsEditor';
import ConditionsSummary from './ConditionsSummary.vue';

const { snapshot, loading, error, reload } = useConditions();
const notice = shallowRef('');
const catalog = computed<Catalog>(() => ({
  products: snapshot.value?.products ?? [],
  giftThreshold: snapshot.value?.giftThreshold ?? 0,
  giftForStaff: snapshot.value?.giftForStaff ?? false,
  revision: snapshot.value?.catalogRevision ?? 1,
  conditionsRevision: snapshot.value?.revision
}));
async function loadSource(): Promise<ConditionsEditorSource> {
  return { snapshot: await conditionsApi.get(), groupId: null };
}
const editor = useConditionsEditor(loadSource, async () => {
  notice.value = 'Общие условия сохранены.';
  await reload();
});
const { command, pending, busy, error: saveError, errors, restored, open, close, reset, save } = editor;
function edit(): void {
  if (snapshot.value && !loading.value && !error.value) {
    notice.value = '';
    open({ snapshot: snapshot.value, groupId: null });
  }
}
</script>
<template>
  <section class="mf-panel conditions-panel" aria-labelledby="global-conditions-title">
    <header class="conditions-heading">
      <div>
        <p class="mf-eyebrow">ПРАВИЛА ПРОДАЖ</p>
        <h2 id="global-conditions-title">Общие условия</h2>
        <p class="mf-muted mt-2">Цены, доступность, скидка сотрудникам и подарок для групп без собственных условий</p>
      </div>
      <v-btn variant="outlined" :disabled="loading || !snapshot || !!error" @click="edit">Изменить условия</v-btn>
    </header>
    <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка общих условий" class="mt-5" />
    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-5"
      >{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert
    >
    <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mt-5">{{ notice }}</v-alert>
    <ConditionsSummary v-if="snapshot && !error" :snapshot="snapshot" class="mt-5" />
  </section>
  <AdminDialog
    :open="!!command"
    title="Общие условия продаж"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    :can-reset="!pending"
    :fields-disabled="!!pending"
    @close="close"
    @save="save"
    @reset="reset"
  >
    <ConditionsFields v-if="command" v-model="command" :errors="errors" :catalog="catalog" />
  </AdminDialog>
</template>
<style scoped>
.conditions-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}
@media (max-width: 700px) {
  .conditions-heading {
    flex-direction: column;
  }
}
</style>
