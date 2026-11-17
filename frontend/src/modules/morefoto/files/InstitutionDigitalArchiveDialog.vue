<script setup lang="ts">
import { computed, watch } from 'vue';
import DigitalArchiveCard from './DigitalArchiveCard.vue';
import { useInstitutionDigitalArchive } from './useInstitutionDigitalArchive';

const props = defineProps<{
  institutionId: string | null;
  institutionName: string;
}>();
const emit = defineEmits<{ close: [] }>();
const { archive, loading, busy, error, notice, open, close, load, create, download, manifest } = useInstitutionDigitalArchive();
watch(
  () => props.institutionId,
  (id) => (id ? open(id) : close()),
  { immediate: true }
);
const visible = computed({
  get: () => props.institutionId !== null,
  set: (value: boolean) => {
    if (!value) emit('close');
  }
});
const pending = computed(() => archive.value?.items.some((item) => item.status === 'pending') ?? false);
</script>
<template>
  <v-dialog v-model="visible" max-width="760" scrollable aria-labelledby="institution-digital-archive-title">
    <v-card class="morefoto-app mf-panel digital-archive-dialog" data-testid="institution-digital-archive">
      <header class="digital-archive-dialog__head">
        <div>
          <p class="mf-eyebrow">ЭЛЕКТРОННЫЕ КАДРЫ</p>
          <h2 id="institution-digital-archive-title">{{ institutionName }}</h2>
        </div>
        <v-btn
          icon="mdi-refresh"
          variant="text"
          :loading="loading"
          :disabled="!!busy"
          aria-label="Обновить электронные архивы"
          @click="load"
        />
      </header>
      <p class="mf-muted">
        Все оплаченные электронные кадры и купленные комплекты учреждения, по группам. Бесплатные копии печати и подарки сюда не входят.
        Закрытия печати ждать не нужно.
      </p>
      <v-alert v-if="error" type="error" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" density="compact" role="status">{{ notice }}</v-alert>
      <v-progress-linear v-if="loading && !archive" indeterminate aria-label="Загрузка электронных архивов" />
      <template v-if="archive">
        <div v-if="archive.canCreate" class="mf-actions">
          <v-btn color="primary" :loading="busy === 'create'" :disabled="!!busy || loading || pending" @click="create"
            >Создать выгрузку</v-btn
          >
        </div>
        <p v-else class="mf-muted">Создать новую выгрузку может организатор. Доступные архивы можно скачать ниже.</p>
        <p v-if="!archive.items.length" class="mf-muted" data-testid="digital-archive-no-releases">
          Электронные выгрузки ещё не создавались.
        </p>
        <p v-if="pending" class="mf-muted" role="status">Сборка выполняется в фоне. Состояние обновляется автоматически.</p>
        <DigitalArchiveCard
          v-for="version in archive.items"
          :key="version.id"
          :archive="version"
          :busy="busy"
          @download="download(version)"
          @manifest="manifest(version)"
        />
        <p v-if="archive.items.length === 20" class="mf-muted">Показаны последние 20 выгрузок.</p>
      </template>
      <p class="mf-muted digital-archive-dialog__note">
        ZIP хранится 7 суток с готовности; состав CSV сохраняется. История покупок включается и после истечения личной ссылки покупателя,
        если оригиналы доступны. Новые покупки попадут в следующую выгрузку. Предел одного ZIP: 10 000 файлов, 20 ГиБ исходников, 50 000
        связей с покупками.
      </p>
      <div class="mf-actions">
        <v-btn variant="outlined" @click="emit('close')">Закрыть</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.digital-archive-dialog {
  display: grid;
  gap: var(--mf-space-4);
  padding: var(--mf-space-6);
  overflow-wrap: anywhere;
}
.digital-archive-dialog > * {
  flex-shrink: 0;
}
.digital-archive-dialog__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.digital-archive-dialog__head h2 {
  font-size: var(--mf-text-xl);
  font-weight: 600;
  line-height: 1.3;
}
.digital-archive-dialog__note {
  font-size: var(--mf-text-sm);
}
@media (max-width: 600px) {
  .digital-archive-dialog {
    padding: var(--mf-space-4);
  }
}
</style>
