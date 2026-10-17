<script setup lang="ts">
import { computed, watch } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import { plural } from '@/components/viz/measures';
import { formatMoment } from '../../handoff/display';
import { fileSize } from '../../orders/live/files-rules';
import { buildAction, versionErrorText, versionTitle } from './archive-rules';
import type { ArchiveVersion } from './archive-types';
import { useGroupArchive } from './useGroupArchive';
import PrintHandoverSection from './PrintHandoverSection.vue';
const props = defineProps<{ groupId: string | null; groupName: string }>();
const emit = defineEmits<{ close: []; changed: [] }>();
const { archive, loading, busy, error, notice, manualLink, open, close, load, build, renew, href, copy, manifest } = useGroupArchive();
watch(
  () => props.groupId,
  (id) => (id ? open(id) : close()),
  { immediate: true }
);
const visible = computed({
  get: () => !!props.groupId,
  set: (value: boolean) => {
    if (!value) emit('close');
  }
});
const action = computed(() => (archive.value ? buildAction(archive.value) : null));
const versions = computed(() => [...(archive.value?.versions ?? [])].reverse());
const statusTone = { pending: 'pending', ready: 'success', failed: 'danger', empty: 'neutral', expired: 'neutral' } as const;
const statusText = { pending: 'Собирается', ready: 'Готов', failed: 'Ошибка', empty: 'Печати нет', expired: 'Удалён по сроку' } as const;
const prints = (count: number) => count + ' ' + plural(count, ['отпечаток', 'отпечатка', 'отпечатков']);
const files = (count: number) => count + ' ' + plural(count, ['файл', 'файла', 'файлов']);
async function command(promise: Promise<void>): Promise<void> {
  await promise;
  emit('changed');
}
function renewLabel(version: ArchiveVersion): string {
  return version.status === 'ready' ? 'Перевыпустить ссылку' : 'Собрать заново';
}
</script>
<template>
  <v-dialog v-model="visible" max-width="680" aria-labelledby="print-archive-title" scrollable>
    <v-card class="morefoto-app mf-panel print-archive" data-testid="print-archive">
      <header class="print-archive__head">
        <h2 id="print-archive-title">Печать · {{ groupName }}</h2>
        <v-btn icon="mdi-refresh" variant="text" density="compact" :loading="loading" aria-label="Обновить блок «Печать»" @click="load" />
      </header>
      <v-alert v-if="notice" type="success" variant="tonal" role="status" density="compact">{{ notice }}</v-alert>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" density="compact">{{ error }}</v-alert>
      <label v-if="manualLink" class="handoff-url"
        >Скопируйте ссылку вручную<input
          :value="manualLink"
          readonly
          aria-label="Ссылка на архив печати"
          @focus="($event.target as HTMLInputElement).select()"
      /></label>
      <v-progress-linear v-if="loading && !archive" indeterminate aria-label="Загрузка архива печати" />
      <template v-if="archive">
        <p v-if="archive.state === 'open'" class="mf-muted" data-testid="print-archive-open">
          <template v-if="archive.deadlineAt"
            >Приём печати идёт до {{ formatMoment(archive.deadlineAt) }}. Архив соберётся сам через час после окончания.</template
          >
          <template v-else>Ссылка группы ещё не передана родителям — архива пока нет.</template>
        </p>
        <p v-else-if="archive.state === 'scheduled'" class="mf-muted">
          Приём печати закончился. Архив соберётся сам после {{ formatMoment(archive.buildFrom) }}.
        </p>
        <p v-else-if="archive.state === 'manual'" class="mf-muted">
          Печать группы закрылась до запуска архивов. Архив собирает организатор кнопкой ниже.
        </p>
        <v-alert
          v-if="archive.versions.length && archive.outsideLines > 0"
          type="warning"
          variant="tonal"
          density="compact"
          data-testid="print-archive-outside"
        >
          После сборки подтвердились оплаты: {{ archive.outsideLines }}
          {{ plural(archive.outsideLines, ['позиция', 'позиции', 'позиций']) }}, {{ prints(archive.outsidePrints) }}. Они попадут в
          дополнение — прежний архив не меняется.
        </v-alert>
        <p v-if="archive.waitingOrders > 0" class="mf-muted" data-testid="print-archive-waiting">
          Ждут подтверждения оплаты: {{ archive.waitingOrders }} {{ plural(archive.waitingOrders, ['заказ', 'заказа', 'заказов']) }}.
          Оплаченные до срока попадут в дополнение.
        </p>
        <div v-if="action" class="mf-actions">
          <v-btn
            color="primary"
            :loading="busy === action"
            :disabled="!!busy"
            data-testid="print-archive-build"
            @click="command(build(action))"
            >{{ action === 'archive' ? 'Собрать архив' : 'Собрать дополнение' }}</v-btn
          >
        </div>
        <ol class="print-archive__versions">
          <li v-for="version in versions" :key="version.id" class="print-archive__version" data-testid="print-archive-version">
            <div class="print-archive__title">
              <strong>{{ versionTitle(version) }}</strong>
              <MfStatus :tone="statusTone[version.status]">{{ statusText[version.status] }}</MfStatus>
            </div>
            <p v-if="version.status === 'empty'" class="mf-muted">Оплаченной печати нет — архив не нужен.</p>
            <template v-else>
              <p class="mf-muted">
                {{ files(version.files) }} · {{ prints(version.prints)
                }}<template v-if="version.bytes"> · {{ fileSize(version.bytes) }}</template> · собран
                {{ formatMoment(version.readyAt ?? version.createdAt) }}
              </p>
              <ul class="print-archive__products">
                <li v-for="product in version.products" :key="product.name + product.format">
                  {{ product.name }} — {{ files(product.files) }}, {{ prints(product.prints) }}
                </li>
              </ul>
              <p v-if="version.status === 'failed'" class="print-archive__error" role="status">{{ versionErrorText(version.error) }}</p>
              <p v-if="version.status === 'ready' && version.expiresAt" class="mf-muted">
                Ссылка и файл хранятся до {{ formatMoment(version.expiresAt) }}.
              </p>
              <p v-if="version.status === 'expired'" class="mf-muted">Файл удалён по сроку хранения, состав сохранён.</p>
              <div class="print-archive__actions">
                <template v-if="version.archiveUrl">
                  <v-btn color="primary" prepend-icon="mdi-download" :href="href(version)" download data-testid="print-archive-download"
                    >Скачать архив</v-btn
                  >
                  <v-btn variant="outlined" prepend-icon="mdi-content-copy" data-testid="print-archive-copy" @click="copy(version)"
                    >Скопировать ссылку</v-btn
                  >
                </template>
                <v-btn
                  variant="text"
                  prepend-icon="mdi-file-delimited-outline"
                  :loading="busy === 'csv:' + version.id"
                  :disabled="!!busy"
                  data-testid="print-archive-csv"
                  @click="manifest(version)"
                  >Состав CSV</v-btn
                >
                <v-btn
                  v-if="archive.canManage && ['ready', 'expired', 'failed'].includes(version.status)"
                  variant="text"
                  :loading="busy === 'renew:' + version.id"
                  :disabled="!!busy"
                  data-testid="print-archive-renew"
                  @click="command(renew(version))"
                  >{{ renewLabel(version) }}</v-btn
                >
              </div>
            </template>
          </li>
        </ol>
        <!-- G2: the print of each paid order is marked as handed over; that sends its closing receipt. -->
        <PrintHandoverSection v-if="groupId" :group-id="groupId" />
        <p class="mf-muted print-archive__note">
          Ссылку можно отправить лаборатории: она открывает только этот архив, без входа. «Перевыпустить ссылку» отключает прежнюю.
        </p>
      </template>
      <div class="mf-actions">
        <v-btn variant="outlined" @click="visible = false">Закрыть</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.print-archive {
  display: grid;
  gap: var(--mf-space-4);
  padding: 24px;
}
.print-archive__head,
.print-archive__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-2);
}
.print-archive__head h2 {
  font-size: 22px;
  line-height: 1.3;
}
.print-archive__versions {
  display: grid;
  gap: var(--mf-space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}
.print-archive__version {
  display: grid;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
}
.print-archive__products {
  margin: 0 0 0 var(--mf-space-5);
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.print-archive__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
.print-archive__error {
  color: var(--mf-tone-danger-fg);
  font-size: var(--mf-text-sm);
}
.print-archive__note {
  font-size: var(--mf-text-sm);
}
@media (max-width: 760px) {
  .print-archive {
    padding: 16px;
  }
  .print-archive__actions .v-btn {
    flex: 1 1 auto;
  }
}
</style>
