<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useDelivery } from '../useDelivery';
import { useDeliveryEditor } from '../useDeliveryEditor';
import { moscowInput } from '../../handoff/rules';
import { randomKey } from '../../orders/services/orders';
import type { DeliveryCommand, DeliveryGroup, TransferBatch } from '../types';
import DeliveryGroups from './DeliveryGroups.vue';
import TransferBatches from './TransferBatches.vue';
import TransferHistory from './TransferHistory.vue';
import DeliveryDialog from './DeliveryDialog.vue';
const route = useRoute(),
  router = useRouter(),
  { data, loading, error, reload } = useDelivery();
const editor = useDeliveryEditor(() => {
  void reload();
});
const { command, busy, error: saveError, errors, restored } = editor;
const institution = computed({
  get: () => String(route.query.institution ?? ''),
  set: (value: string) => {
    void router.replace({ query: { ...route.query, institution: value || undefined, shoot: undefined, group: undefined } });
  }
});
const shoot = computed({
  get: () => String(route.query.shoot ?? ''),
  set: (value: string) => {
    void router.replace({ query: { ...route.query, shoot: value || undefined, group: undefined } });
  }
});
const institutions = computed(() => [
  { title: 'Все учреждения', value: '' },
  ...Array.from(
    new Map(data.value?.groups.map((g) => [g.institutionId, { title: g.institutionName, value: g.institutionId }]) ?? []).values()
  )
]);
const shoots = computed(() => [
  { title: 'Все съёмки', value: '' },
  ...Array.from(
    new Map(
      data.value?.groups
        .filter((g) => !institution.value || g.institutionId === institution.value)
        .map((g) => [g.shootId, { title: g.shootName, value: g.shootId }]) ?? []
    ).values()
  )
]);
const matches = (g: { institutionId: string; shootId: string }) =>
  (!institution.value || g.institutionId === institution.value) && (!shoot.value || g.shootId === shoot.value);
const groups = computed(() => data.value?.groups.filter(matches) ?? []);
const batches = computed(() => data.value?.batches.filter(matches) ?? []);
const transfers = computed(() => data.value?.transfers.filter(matches) ?? []);
const selected = computed(() =>
  command.value?.kind === 'transfer'
    ? data.value?.batches.find((b) => b.id === command.value?.targetId)
    : data.value?.groups.find((g) => g.id === command.value?.targetId)
);
function action(kind: DeliveryCommand['kind'], item: DeliveryGroup | TransferBatch) {
  editor.open(() => {
    const current =
      kind === 'transfer' ? data.value?.batches.find((b) => b.id === item.id) : data.value?.groups.find((g) => g.id === item.id);
    return {
      id: randomKey(),
      kind,
      targetId: item.id,
      signature: current?.signature ?? '',
      date: moscowInput(data.value!.now),
      responsible: data.value!.actorName,
      receiver: '',
      comment: '',
      confirmed: false
    };
  });
}
function update(patch: Partial<DeliveryCommand>) {
  if (command.value && !busy.value) Object.assign(command.value, patch);
}
</script>
<template>
  <div class="delivery-stack" data-testid="delivery-workspace">
    <header class="mf-page-heading">
      <p class="mf-eyebrow">ПАКЕТЫ → УЧРЕЖДЕНИЕ</p>
      <h1>Готовность и доставка</h1>
      <p class="mf-muted mt-3">
        От проверенного пакета до получения в учреждении. Срок каждой группы — семь календарных дней после её закрытия.
      </p>
    </header>
    <p v-if="loading && !data" role="status">Загружаем доставку…</p>
    <v-alert v-else-if="error" role="alert" type="error" variant="tonal"
      >{{ error }}<v-btn variant="text" @click="reload">Повторить загрузку</v-btn></v-alert
    >
    <template v-if="data">
      <p v-if="!data.editable" class="mf-muted">
        {{ data.staff ? 'Состояние учреждений в вашей области.' : 'Сводка доступных вам групп.' }} Готовность и передачу отмечает
        организатор.
      </p>
      <div class="delivery-filters">
        <v-select v-model="institution" label="Учреждение" aria-label="Учреждение" :items="institutions" hide-details /><v-select
          v-model="shoot"
          label="Съёмка"
          aria-label="Съёмка"
          :items="shoots"
          hide-details
        />
      </div>
      <TransferBatches v-if="data.staff" :batches="batches" :editable="data.editable" @transfer="action('transfer', $event)" />
      <DeliveryGroups :groups="groups" :staff="data.staff" @action="action" />
      <TransferHistory :transfers="transfers" />
      <DeliveryDialog
        :command="command"
        :selected="selected"
        :busy="busy"
        :error="saveError"
        :errors="errors"
        :restored="restored"
        @close="editor.close"
        @save="editor.save"
        @reset="editor.reset"
        @update="update"
      />
    </template>
  </div>
</template>
<style>
.delivery-stack {
  display: grid;
  gap: 24px;
  min-width: 0;
}
.delivery-stack > * {
  min-width: 0;
}
.delivery-stack h1 {
  font-size: clamp(27px, 3vw, 36px);
  line-height: 1.2;
}
.delivery-stack h2 {
  font-size: 23px;
  line-height: 1.3;
}
.delivery-stack h3 {
  font-size: 19px;
  line-height: 1.4;
}
.delivery-filters,
.delivery-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}
.delivery-card {
  min-width: 0;
  overflow-wrap: anywhere;
}
.delivery-card p {
  margin-top: 10px;
}
.delivery-row {
  display: flex;
  gap: 16px;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
}
.delivery-totals {
  font-size: 23px;
  color: var(--mf-color-link);
  font-weight: 600;
}
.delivery-late {
  color: var(--mf-tone-danger-fg);
  font-weight: 600;
}
.delivery-lines {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 16px;
  margin: 20px 0;
}
.delivery-lines li {
  border-top: 1px solid var(--mf-color-border);
  padding-top: 16px;
}
.delivery-address {
  border-left: 3px solid var(--mf-color-primary);
  padding-left: 14px;
}
.delivery-stack .v-btn {
  max-width: 100%;
  height: auto;
  min-height: 44px;
  padding-block: 10px;
  white-space: normal;
}
.delivery-stack .v-btn__content {
  white-space: normal;
  overflow-wrap: anywhere;
}
.delivery-stack .v-chip {
  max-width: 100%;
  height: auto;
  min-height: 32px;
  padding-block: 6px;
}
.delivery-stack .v-chip__content {
  white-space: normal;
}
.delivery-history {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 20px;
}
.delivery-history summary {
  cursor: pointer;
  min-height: 44px;
  align-content: center;
  color: var(--mf-color-link);
}
.delivery-empty {
  border: 1px dashed var(--mf-color-border-hover);
  padding: 24px;
  border-radius: 12px;
  color: var(--mf-color-text-secondary);
}
.delivery-staff {
  font-size: 13px;
  color: var(--mf-color-text-secondary);
}
.delivery-ready {
  border-top: 4px solid var(--mf-color-primary);
}
.delivery-muted {
  font-size: 14px;
  color: var(--mf-color-text-secondary);
}
@media (max-width: 900px) {
  .delivery-filters,
  .delivery-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 600px) {
  .delivery-stack {
    gap: 20px;
  }
  .delivery-totals {
    font-size: 21px;
  }
}
</style>
