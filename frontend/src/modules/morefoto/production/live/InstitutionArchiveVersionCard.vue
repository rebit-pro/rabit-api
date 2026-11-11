<script setup lang="ts">
import MfStatus from '@/components/status/MfStatus.vue';
import { plural } from '@/components/viz/measures';
import { formatMoment } from '../../handoff/display';
import { fileSize } from '../../orders/live/files-rules';
import { versionErrorText } from './archive-rules';
import { institutionArchiveError, institutionVersionTitle } from './institution-archive-rules';
import type { InstitutionArchiveVersion } from './institution-archive-types';

defineProps<{
  version: InstitutionArchiveVersion;
  canManage: boolean;
  busy: string;
  featured: boolean;
}>();
const emit = defineEmits<{ download: []; manifest: []; renew: [] }>();
const tone = {
  pending: 'pending',
  ready: 'success',
  failed: 'danger',
  empty: 'neutral',
  expired: 'neutral'
} as const;
const status = {
  pending: 'Готовится',
  ready: 'Готов',
  failed: 'Ошибка',
  empty: 'В составе нет печати',
  expired: 'Срок хранения истёк'
} as const;
</script>
<template>
  <article
    class="institution-archive-version"
    :class="{ 'institution-archive-version--featured': featured }"
    data-testid="institution-archive-version"
  >
    <div class="institution-archive-version__head">
      <h3>{{ institutionVersionTitle(version) }}</h3>
      <MfStatus :tone="tone[version.status]">{{ status[version.status] }}</MfStatus>
    </div>
    <p v-if="version.kind === 'full'" class="mf-muted">
      Содержит прежнюю печать вместе с новыми заданиями. Для допечатки используйте дополнение.
    </p>
    <p v-else class="mf-muted">Только новые позиции. Прежние отпечатки в дополнение не включены.</p>
    <p class="institution-archive-version__counts">
      <strong>{{ version.prints }} шт.</strong> · {{ version.files }} {{ plural(version.files, ['файл', 'файла', 'файлов']) }} ·
      {{ version.groups.length }} {{ plural(version.groups.length, ['группа', 'группы', 'групп']) }}
      <template v-if="version.bytes"> · {{ fileSize(version.bytes) }}</template>
    </p>
    <p class="mf-muted">Состав от {{ formatMoment(version.createdAt) }}. Группы: {{ version.groups.join(', ') }}.</p>
    <p v-if="version.status === 'ready'" class="mf-muted">Файл доступен до {{ formatMoment(version.expiresAt) }}.</p>
    <p v-if="version.status === 'expired'" class="mf-muted">Состав сохранён. Организатор может собрать тот же архив заново.</p>
    <p v-if="version.status === 'failed'" class="institution-archive-version__error" role="status">
      {{ institutionArchiveError(version.error) ?? versionErrorText(version.error) }}
    </p>
    <div class="mf-actions">
      <v-btn
        v-if="version.status === 'ready'"
        :variant="featured ? 'flat' : 'outlined'"
        color="primary"
        prepend-icon="mdi-download"
        :disabled="!!busy"
        :loading="busy === 'download:' + version.id"
        data-testid="institution-archive-download"
        @click="emit('download')"
        >{{ version.kind === 'full' ? 'Скачать полный архив' : 'Скачать дополнение' }}</v-btn
      >
      <v-btn variant="text" :disabled="!!busy" :loading="busy === 'csv:' + version.id" @click="emit('manifest')">Состав CSV</v-btn>
      <v-btn
        v-if="canManage && ['ready', 'failed', 'expired'].includes(version.status)"
        variant="text"
        :disabled="!!busy"
        :loading="busy === 'renew:' + version.id"
        data-testid="institution-archive-renew"
        @click="emit('renew')"
        >{{ version.status === 'ready' ? 'Отозвать прежний доступ' : 'Собрать заново' }}</v-btn
      >
    </div>
  </article>
</template>
<style scoped>
.institution-archive-version {
  display: grid;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  overflow-wrap: anywhere;
}
.institution-archive-version--featured {
  background: var(--mf-color-surface);
}
.institution-archive-version__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-2);
}
.institution-archive-version__head h3 {
  font-size: var(--mf-text-lg);
  font-weight: 600;
}
.institution-archive-version__counts {
  font-size: var(--mf-text-sm);
}
.institution-archive-version__error {
  color: var(--mf-tone-danger-fg);
}
</style>
