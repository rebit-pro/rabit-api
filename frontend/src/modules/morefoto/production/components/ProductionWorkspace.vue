<script setup lang="ts">
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useProduction } from '../useProduction';
import { useProductionCommand } from '../useProductionCommand';
import { randomKey } from '../../orders/services/orders';
import type { ProductionCommand } from '../types';
import ProductionQueue from './ProductionQueue.vue';
import ProductionDetail from './ProductionDetail.vue';
import ProductionDialog from './ProductionDialog.vue';
const route = useRoute(),
  { data, loading, error, reload } = useProduction();
const item = computed(() => data.value?.groups.find((g) => g.group.id === route.params.groupId));
const {
  command,
  busy,
  error: saveError,
  restored,
  open,
  reset,
  close,
  save
} = useProductionCommand(() => {
  void reload();
});
watch(() => route.params.groupId, close);
function action(kind: ProductionCommand['kind'], orderId?: string, packed?: boolean) {
  if (!item.value) return;
  open(() => ({
    id: randomKey(),
    groupId: item.value!.group.id,
    revision: item.value!.job?.revision ?? 0,
    signature: item.value!.plan.signature,
    kind,
    orderId,
    packed,
    reason:
      kind === 'version'
        ? item.value!.job
          ? ''
          : 'Первичное задание'
        : kind === 'start'
          ? 'Состав проверен, запуск учтён'
          : packed
            ? 'Состав и подпись пакета проверены'
            : ''
  }));
}
</script>
<template>
  <div class="production-stack" data-testid="production-workspace">
    <RouterLink v-if="route.params.groupId" :to="{ path: '/cabinet/production', query: route.query }" class="mf-back"
      >← К производству</RouterLink
    >
    <p v-if="loading && !data" role="status">Загружаем производство…</p>
    <v-alert v-else-if="error" type="error" variant="tonal" role="alert"
      >{{ error }}<v-btn variant="text" @click="reload">Повторить загрузку</v-btn></v-alert
    >
    <template v-if="data">
      <p v-if="!data.editable" class="mf-muted">
        Просмотр производства своего учреждения. Формирование, запуск и комплектацию выполняет организатор.
      </p>
      <template v-if="route.params.groupId"
        ><template v-if="item"
          ><ProductionDetail :key="item.group.id" :item="item" :editable="data.editable" @action="action" /><ProductionDialog
            :command="command"
            :item="item"
            :busy="busy"
            :error="saveError"
            :restored="restored"
            @close="close"
            @save="save"
            @reset="reset"
            @reason="command && (command.reason = $event)"
        /></template>
        <section v-else class="mf-panel">
          <h1>Группа недоступна</h1>
          <p class="mt-3">Проверьте ссылку и назначенное учреждение.</p>
        </section></template
      >
      <template v-else
        ><header class="mf-page-heading">
          <p class="mf-eyebrow">ОПЛАТА → ПЕЧАТЬ → ПАКЕТЫ</p>
          <h1>Производство и комплектация</h1>
          <p class="mf-muted mt-3">
            Оплаченные фотографии после закрытия группы. Один номер задания, проверенные версии и отдельный пакет для каждого заказа.
          </p>
        </header>
        <ProductionQueue :groups="data.groups"
      /></template>
    </template>
  </div>
</template>
<style>
.production-stack {
  display: grid;
  gap: 24px;
  min-width: 0;
}
.production-stack > *,
.production-stack .mf-panel {
  min-width: 0;
}
.production-demo {
  font-size: 13px;
  color: var(--mf-color-text-secondary);
  border-left: 3px solid var(--mf-color-primary);
  padding: 4px 12px;
}
.production-filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.production-groups {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}
.production-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}
.production-heading h1 {
  font-size: clamp(26px, 3vw, 36px);
  line-height: 1.2;
}
.production-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  padding: 20px;
  background: var(--mf-color-bg);
  border-radius: 12px;
}
.production-metrics div {
  display: grid;
  gap: 8px;
  align-content: start;
}
.production-metrics strong {
  font-size: 32px;
  line-height: 1.2;
  color: var(--mf-color-link);
}
.production-metrics span {
  font-size: 14px;
  color: var(--mf-color-text-secondary);
}
.production-version {
  max-width: 240px;
  min-width: 180px;
}
.production-package {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: center;
  padding: 20px 0;
  border-top: 1px solid var(--mf-color-border);
  margin-top: 16px;
}
.production-package > div {
  min-width: 0;
}
.production-package p {
  margin-top: 8px;
}
.production-history {
  padding-left: 20px;
  margin-top: 20px;
  display: grid;
  gap: 16px;
}
.production-stack .v-btn {
  max-width: 100%;
  white-space: normal;
  height: auto;
  min-height: 44px;
  padding-top: 10px;
  padding-bottom: 10px;
}
.production-stack .v-btn__content {
  white-space: normal;
  overflow-wrap: anywhere;
}
.production-stack .v-chip {
  height: auto;
  min-height: 32px;
  max-width: 100%;
  padding-block: 6px;
}
.production-stack .v-chip__content {
  white-space: normal;
}
.production-group h2,
.production-heading h1,
.production-package,
.production-history {
  overflow-wrap: anywhere;
}
@media (max-width: 900px) {
  .production-filters,
  .production-groups {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 600px) {
  .production-metrics {
    grid-template-columns: 1fr;
    gap: 20px;
    padding: 16px;
  }
  .production-metrics div {
    grid-template-columns: 60px 1fr;
    align-items: center;
  }
  .production-package {
    align-items: flex-start;
    flex-direction: column;
  }
  .production-version {
    max-width: 100%;
    width: 100%;
  }
  .production-heading {
    gap: 16px;
  }
}
</style>
