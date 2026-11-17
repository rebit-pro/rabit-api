<script setup lang="ts">
import { formatMoment } from '../handoff/display';
import { plural } from '@/components/viz/measures';
import { digitalArchiveError, digitalArchiveStatus } from './institution-digital-rules';
import type { InstitutionDigitalArchive } from './institution-digital-types';

defineProps<{ archive: InstitutionDigitalArchive; busy: string }>();
const emit = defineEmits<{ download: []; manifest: [] }>();
</script>
<template>
  <section class="digital-archive-card mf-panel" :data-archive-id="archive.id">
    <header class="digital-archive-card__head">
      <strong>{{ digitalArchiveStatus(archive) }}</strong>
      <span>{{ archive.files }} {{ plural(archive.files, ['файл', 'файла', 'файлов']) }}</span>
    </header>
    <p class="mf-muted">Состав на {{ formatMoment(archive.capturedAt) }}</p>
    <p v-if="archive.status === 'ready' && archive.expiresAt" class="mf-muted">Скачать до {{ formatMoment(archive.expiresAt) }}</p>
    <p v-if="archive.status === 'empty'" class="mf-muted">Оплаченных купленных электронных кадров и комплектов в этом снимке нет.</p>
    <p v-if="archive.status === 'expired'" class="mf-muted">
      Манифест сохранён. Организатор может создать новую выгрузку из доступных оригиналов.
    </p>
    <p v-if="archive.error" class="digital-archive-card__error" role="status">
      {{ digitalArchiveError(archive.error) }}
    </p>
    <div class="mf-actions">
      <v-btn
        v-if="archive.status === 'ready'"
        color="primary"
        prepend-icon="mdi-download"
        :loading="busy === 'download:' + archive.id"
        :disabled="!!busy"
        @click="emit('download')"
        >Скачать ZIP</v-btn
      >
      <v-btn variant="outlined" :loading="busy === 'manifest:' + archive.id" :disabled="!!busy" @click="emit('manifest')">Состав CSV</v-btn>
    </div>
  </section>
</template>
<style scoped>
.digital-archive-card {
  display: grid;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4);
}
.digital-archive-card__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--mf-space-2);
}
.digital-archive-card__error {
  color: var(--mf-color-error);
}
</style>
