<script setup lang="ts">
import { computed, watch } from 'vue';
import { plural } from '@/components/viz/measures';
import { formatMoment } from '../../handoff/display';
import { versionErrorText } from './archive-rules';
import { canSupplementInstitution, institutionArchiveError, latestInstitutionArchive } from './institution-archive-rules';
import { useInstitutionArchive } from './useInstitutionArchive';
import InstitutionArchiveVersionCard from './InstitutionArchiveVersionCard.vue';

const props = defineProps<{
  institutionId: string | null;
  institutionName: string;
}>();
const emit = defineEmits<{ close: [] }>();
const { archive, loading, busy, error, notice, open, close, load, supplement, renew, download, manifest } = useInstitutionArchive();
watch(
  () => props.institutionId,
  (id) => (id ? open(id) : close()),
  { immediate: true }
);
const visible = computed({
  get: () => !!props.institutionId,
  set: (value: boolean) => {
    if (!value) emit('close');
  }
});
const available = computed(() => (archive.value ? latestInstitutionArchive(archive.value) : null));
const versions = computed(() => [...(archive.value?.versions ?? [])].reverse());
const canSupplement = computed(() => (archive.value ? canSupplementInstitution(archive.value) : false));
</script>
<template>
  <v-dialog v-model="visible" max-width="760" scrollable aria-labelledby="institution-print-archive-title">
    <v-card class="morefoto-app mf-panel institution-print-archive" data-testid="institution-print-archive">
      <header class="institution-print-archive__head">
        <div>
          <p class="mf-eyebrow">ПЕЧАТЬ УЧРЕЖДЕНИЯ</p>
          <h2 id="institution-print-archive-title">{{ institutionName }}</h2>
        </div>
        <v-btn icon="mdi-refresh" variant="text" :loading="loading" aria-label="Обновить архив учреждения" @click="load" />
      </header>
      <v-alert v-if="error" type="error" variant="tonal" role="alert" density="compact">{{ error }}</v-alert>
      <v-alert v-if="notice" type="success" variant="tonal" role="status" density="compact">{{ notice }}</v-alert>
      <v-progress-linear v-if="loading && !archive" indeterminate aria-label="Загрузка архива учреждения" />
      <template v-if="archive">
        <section v-if="archive.blockers.length" class="institution-print-archive__waiting" data-testid="institution-archive-blockers">
          <h3>
            {{ available ? 'Ожидаем следующий выпуск' : 'Приём печати ещё идёт' }}
          </h3>
          <p class="mf-muted">Для нового полного архива нужно закрытие печати всех групп. Электронные продажи ожидание не продлевают.</p>
          <ul>
            <li v-for="group in archive.blockers" :key="group.id">
              <strong>{{ group.name }}</strong> · {{ group.shootName }} —
              {{ group.deadlineAt ? 'до ' + formatMoment(group.deadlineAt) : 'ссылка ещё не передана' }}
            </li>
          </ul>
          <p v-if="available" class="mf-muted" data-testid="institution-archive-previous">
            Прежний полный архив доступен ниже. Новые открытые съёмки в него ещё не входят.
          </p>
        </section>
        <p v-else-if="archive.state === 'scheduled'" class="mf-muted">
          Приём печати закрыт. Автоматическая сборка — через час после последнего срока<template v-if="archive.buildFrom"
            >, после {{ formatMoment(archive.buildFrom) }}</template
          >.
        </p>
        <p v-if="archive.error" class="institution-print-archive__error" role="status">
          {{ institutionArchiveError(archive.error) ?? versionErrorText(archive.error) }}
        </p>
        <p v-if="archive.state === 'empty' && archive.outsideLines === 0" class="mf-muted" data-testid="institution-archive-empty">
          Оплаченной печати нет. Пустые задания и ZIP не создаются.
        </p>
        <v-alert v-if="archive.outsideLines > 0 && archive.versions.length" type="warning" variant="tonal" density="compact">
          После снимка подтвердились оплаты: {{ archive.outsideLines }}
          {{ plural(archive.outsideLines, ['позиция', 'позиции', 'позиций']) }}. Организатор может собрать дополнение с новыми отпечатками.
        </v-alert>
        <p v-if="archive.waitingOrders > 0" class="mf-muted">
          Ждут подтверждения: {{ archive.waitingOrders }} {{ plural(archive.waitingOrders, ['заказ', 'заказа', 'заказов']) }}. Они не
          задерживают архив; своевременная оплата после подтверждения попадёт в дополнение.
        </p>
        <div v-if="canSupplement" class="mf-actions">
          <v-btn
            variant="outlined"
            :loading="busy === 'supplement'"
            :disabled="!!busy"
            data-testid="institution-archive-supplement"
            @click="supplement"
            >Собрать дополнение</v-btn
          >
        </div>
        <div class="institution-print-archive__versions">
          <InstitutionArchiveVersionCard
            v-for="version in versions"
            :key="version.id"
            :version="version"
            :featured="version.id === available?.id"
            :can-manage="archive.canManage"
            :busy="busy"
            @download="download(version)"
            @manifest="manifest(version)"
            @renew="renew(version)"
          />
        </div>
        <p class="mf-muted institution-print-archive__note">
          Один ZIP: учреждение → группа → продукция → файл или Nшт/файл. Полный архив содержит прежние задания; повторное скачивание не
          означает новую печать. Доступ проверяется при каждом скачивании.
        </p>
      </template>
      <div class="mf-actions">
        <v-btn variant="outlined" @click="emit('close')">Закрыть</v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.institution-print-archive {
  display: grid;
  gap: var(--mf-space-4);
  padding: var(--mf-space-6);
  overflow-wrap: anywhere;
}
.institution-print-archive__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.institution-print-archive__head h2 {
  font-size: var(--mf-text-xl);
  font-weight: 600;
  line-height: 1.3;
}
.institution-print-archive__waiting,
.institution-print-archive__versions {
  display: grid;
  gap: var(--mf-space-3);
}
.institution-print-archive__waiting h3 {
  font-size: var(--mf-text-base);
  font-weight: 600;
}
.institution-print-archive__waiting ul {
  padding-left: var(--mf-space-5);
  font-size: var(--mf-text-sm);
}
.institution-print-archive__note {
  font-size: var(--mf-text-sm);
}
.institution-print-archive__error {
  color: var(--mf-tone-danger-fg);
}
@media (max-width: 760px) {
  .institution-print-archive {
    padding: var(--mf-space-4);
  }
}
</style>
