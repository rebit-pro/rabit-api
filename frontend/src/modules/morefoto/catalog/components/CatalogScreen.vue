<script setup lang="ts">
import { shallowRef } from 'vue';
import AdminDialog from '../../management/components/AdminDialog.vue';
import ProductFields from '../../management/components/ProductFields.vue';
import CatalogTable from './CatalogTable.vue';
import { useCatalog } from '../useCatalog';
import { useProductEditor } from '../useProductEditor';
import type { CatalogProduct } from '../api';
const { snapshot, page, pages, loading, error, reload } = useCatalog();
const notice = shallowRef('');
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
    open(snapshot.value.data.revision, product);
  }
}
</script>
<template>
  <header class="mf-page-heading">
    <p class="mf-eyebrow">АССОРТИМЕНТ</p>
    <h1>Каталог и цены</h1>
    <p class="mf-muted">Продукция, стоимость и доступность для покупки</p>
    <div class="mf-actions mt-5">
      <v-btn prepend-icon="mdi-plus" :disabled="loading || !snapshot || !!error" @click="edit()">Новая продукция</v-btn>
      <v-btn variant="outlined" :disabled="loading" @click="reload()">Обновить каталог</v-btn>
    </div>
  </header>
  <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка каталога" class="mb-5" />
  <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mb-5">{{ error }}</v-alert>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <template v-if="snapshot && !error">
    <p class="mf-muted mb-4">Всего позиций: {{ snapshot.meta.total }}</p>
    <CatalogTable :products="snapshot.data.items" :disabled="loading" @edit="edit" />
    <nav v-if="pages > 1" class="mf-actions mt-5" aria-label="Страницы каталога">
      <v-btn variant="outlined" :disabled="loading || page === 1" @click="reload(page - 1)">Предыдущая</v-btn>
      <span role="status">Страница {{ page }} из {{ pages }}</span>
      <v-btn variant="outlined" :disabled="loading || page === pages" @click="reload(page + 1)">Следующая</v-btn>
    </nav>
  </template>
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
