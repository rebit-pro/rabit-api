<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminDialog from '../../management/components/AdminDialog.vue';
import ProductFields from '../../management/components/ProductFields.vue';
import GlobalConditionsPanel from '../../conditions/components/GlobalConditionsPanel.vue';
import UiBulkNotice from '../../ui/components/UiBulkNotice.vue';
import UiRemoveDialog from '../../ui/components/UiRemoveDialog.vue';
import { bulkNotice, type BulkNotice } from '../../ui/removal';
import CatalogTable from './CatalogTable.vue';
import { useCatalog } from '../useCatalog';
import { useProductEditor } from '../useProductEditor';
import type { CatalogProduct } from '../api';
const { snapshot, loading, busy: acting, error, selected, truncated, reload, products, remove, setActive } = useCatalog();
const route = useRoute();
const router = useRouter();
// The tab lives in the address, so a reload or a shared link opens the same part of the catalog.
const tab = computed<'products' | 'conditions'>({
  get: () => (route.query.tab === 'conditions' ? 'conditions' : 'products'),
  set: (value) => void router.replace({ query: { ...route.query, tab: value === 'conditions' ? 'conditions' : undefined } })
});
const notice = shallowRef('');
const result = shallowRef<BulkNotice | null>(null);
// Names are taken when the dialog opens: the list reloads under it before it closes.
const removal = shallowRef<{ ids: string[]; names: string[] } | null>(null);
const {
  command,
  existingId,
  pending,
  busy,
  error: saveError,
  errors,
  restored,
  open,
  close,
  reset,
  save
} = useProductEditor(async () => {
  notice.value = 'Изменения сохранены.';
  await reload();
});
function edit(product?: CatalogProduct): void {
  if (snapshot.value && !loading.value && !error.value) {
    notice.value = '';
    result.value = null;
    open(snapshot.value.data.revision, product);
  }
}
function askRemove(ids: string[]): void {
  notice.value = '';
  result.value = null;
  removal.value = { ids, names: products(ids).map((product) => product.name) };
}
async function confirmRemove(): Promise<void> {
  if (!removal.value) return;
  try {
    result.value = bulkNotice('Удалено', await remove(removal.value.ids), 'Позиции убраны из каталога и условий групп.');
  } finally {
    removal.value = null;
  }
}
async function activate(ids: string[], active: boolean): Promise<void> {
  notice.value = '';
  result.value = null;
  const outcome = await setActive(ids, active);
  result.value = outcome.total
    ? bulkNotice(active ? 'Возвращено в продажу' : 'Снято с продажи', outcome)
    : { tone: 'success', text: active ? 'Выбранная продукция уже в продаже.' : 'Выбранная продукция уже снята с продажи.', failures: [] };
}
</script>
<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">АССОРТИМЕНТ</p>
    <h1>Каталог и цены</h1>
    <p class="mf-muted">Продукция, стоимость и доступность для покупки</p>
  </header>
  <v-tabs v-model="tab" class="catalog-tabs mb-6" aria-label="Разделы каталога" color="primary">
    <v-tab value="products">Продукция</v-tab>
    <v-tab value="conditions">Общие условия</v-tab>
  </v-tabs>
  <GlobalConditionsPanel v-if="tab === 'conditions'" />
  <template v-else>
    <div class="mf-actions mb-5">
      <v-btn prepend-icon="mdi-plus" :disabled="loading || acting || !snapshot || !!error" @click="edit()">Новая продукция</v-btn>
      <v-btn variant="outlined" :disabled="loading || acting" @click="reload()">Обновить каталог</v-btn>
    </div>
    <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка каталога" class="mb-5" />
    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
    <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
    <UiBulkNotice v-if="result" :notice="result" class="mb-5" data-testid="catalog-result" />
    <template v-if="snapshot && !error">
      <p v-if="truncated" class="mf-muted mb-3">Показаны первые {{ snapshot.data.items.length }} из {{ snapshot.meta.total }}.</p>
      <CatalogTable
        v-model:selected="selected"
        :products="snapshot.data.items"
        :disabled="loading || acting"
        @edit="edit"
        @remove="askRemove"
        @activate="activate"
      />
    </template>
  </template>
  <UiRemoveDialog
    :open="!!removal"
    :title="removal?.names.length === 1 ? 'Удалить продукцию?' : 'Удалить продукцию: ' + removal?.names.length + '?'"
    :names="removal?.names ?? []"
    :busy="acting"
    testid="catalog-remove-dialog"
    @confirm="confirmRemove"
    @close="removal = null"
  >
    Позиция исчезнет из каталога и условий групп. Если её уже покупали, удалить нельзя — сервер ответит отказом, а позицию можно снять с
    продажи: оформленные заказы сохранят её название и цену.
  </UiRemoveDialog>
  <AdminDialog
    :open="!!command"
    :title="existingId ? 'Редактирование продукции' : 'Новая продукция'"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    :can-reset="!pending"
    :fields-disabled="!!pending"
    @close="close"
    @save="save"
    @reset="reset"
  >
    <ProductFields v-if="command" v-model="command" :errors="errors" :existing="!!existingId" live />
  </AdminDialog>
</template>
