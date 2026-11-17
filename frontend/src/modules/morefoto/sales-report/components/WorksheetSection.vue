<script setup lang="ts">
import { computed } from 'vue';
import { money } from '../rules';
import { worksheetMoment, worksheetSources, type Worksheet } from '../worksheet';
import WorksheetTable from './WorksheetTable.vue';

const props = defineProps<{ report: Worksheet; selectedSource: string }>();
const emit = defineEmits<{ source: [value: unknown] }>();
const sources = computed(() => worksheetSources(props.report));
const final = computed(() => props.report.mode === 'final');
</script>

<template>
  <section class="worksheet-section" aria-labelledby="worksheet-title" data-testid="production-worksheet">
    <div class="worksheet-heading">
      <div>
        <h2 id="worksheet-title">Ведомость по заказам и файлам печати</h2>
        <p class="mf-muted">Сформировано {{ worksheetMoment(report.generatedAt) }} (МСК)</p>
      </div>
      <v-select
        :model-value="selectedSource"
        :items="sources"
        label="Вид и версия ведомости"
        aria-label="Вид и версия ведомости"
        density="compact"
        hide-details
        data-testid="worksheet-source"
        @update:model-value="emit('source', $event)"
      />
    </div>
    <v-alert v-if="!final" type="info" variant="tonal" data-testid="worksheet-preview">
      Предварительная — не задание лаборатории. Показывает текущие покупки; неоплаченные и поздние заказы отделены от итогов.
    </v-alert>
    <v-alert v-else-if="report.archive" type="info" variant="tonal" data-testid="worksheet-version">
      <strong
        >Окончательная · {{ report.archive.type === 'institution' ? 'Учреждение' : 'Группа' }} · версия {{ report.archive.number }} ·
        {{ report.archive.kind === 'full' ? 'Полный выпуск' : 'Дополнение' }}</strong
      >
      <p>ID: {{ report.archive.id }}. Снимок {{ worksheetMoment(report.archive.createdAt) }} (МСК).</p>
      <p>{{ report.archive.files }} файлов · {{ report.archive.prints }} отпечатков в выбранном архиве.</p>
      <p v-if="report.archive.type === 'institution' && report.archive.kind === 'full'">
        Накопительный состав: прежние задания и новые. Скачивание не является новым заданием печати.
      </p>
      <p>Печатные строки и итог относятся только к этой версии ZIP/CSV. Электронные покупки показаны отдельно на момент формирования.</p>
    </v-alert>
    <p v-if="!report.finalAllowed" class="mf-muted">Окончательные версии доступны организатору и куратору своего учреждения.</p>
    <p v-else-if="!report.versions.length" class="mf-muted">
      Для выбора сохранённой версии откройте учреждение или группу. Если версии ещё нет, доступна предварительная ведомость.
    </p>
    <p class="worksheet-explanation">
      ФИО принадлежит всему заказу. Несколько детей обозначены явно; совпадение имён не объединяет заказы. В общем файле отдельно указаны
      вклад позиции и общее число отпечатков.
    </p>
    <p v-if="!report.groups.length" class="mf-muted">В выбранной области пока нет групп.</p>
    <section v-for="group in report.groups" :key="group.id" class="worksheet-group">
      <header>
        <h3>{{ group.name }}</h3>
        <p class="mf-muted">{{ group.institutionName }} · {{ group.shootName }}</p>
      </header>
      <WorksheetTable :key="(report.archive?.id ?? 'preview') + group.id" :group="group" :columns="report.columns" :final="final" />
      <p class="worksheet-group-total">
        Итого по группе: <strong>{{ money(group.total) }}</strong> · {{ group.prints }} отп.
      </p>
      <details v-if="group.outsideOrders.length" class="worksheet-other">
        <summary>Текущие заказы вне выбранной печатной версии · {{ group.outsideOrders.length }}</summary>
        <WorksheetTable :group="group" :columns="report.columns" :final="final" :items="group.outsideOrders" reference />
      </details>
      <details v-if="group.deferredOrders.length" class="worksheet-other" data-testid="worksheet-deferred">
        <summary>Неоплаченные и поздние заказы · {{ group.deferredOrders.length }} — не входят в итог</summary>
        <WorksheetTable :group="group" :columns="report.columns" :final="final" :items="group.deferredOrders" reference />
      </details>
    </section>
    <footer class="worksheet-totals">
      <p>
        Итого по выбранной области: <strong data-testid="worksheet-total">{{ money(report.total) }}</strong> · {{ report.prints }} отп.
      </p>
      <p class="mf-muted">{{ final ? 'Отдельно текущие' : 'В том числе' }} электронные покупки: {{ money(report.electronicTotal) }}.</p>
      <p class="mf-muted">
        J4-копии к печати и подарочные комплекты не увеличивают продажи. Конкретные готовые файлы комплекта показаны по правилу J1 на момент
        формирования.
      </p>
    </footer>
  </section>
</template>

<style scoped>
.worksheet-section,
.worksheet-group {
  display: grid;
  gap: var(--mf-space-4);
  min-width: 0;
}
.worksheet-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: start;
  justify-content: space-between;
  gap: var(--mf-space-4);
}
.worksheet-heading > div:first-child {
  flex: 1 1 320px;
}
.worksheet-heading > .v-input {
  flex: 1 1 360px;
  min-width: 0;
}
h2,
h3 {
  font-family: var(--mf-font-display);
  font-weight: var(--mf-weight-semibold);
  overflow-wrap: anywhere;
}
h2 {
  font-size: var(--mf-text-lg);
}
h3 {
  font-size: var(--mf-text-base);
}
.worksheet-explanation,
.worksheet-group-total,
.worksheet-totals {
  font-variant-numeric: tabular-nums;
}
.worksheet-group {
  margin-block: var(--mf-space-4);
}
.worksheet-other > summary {
  min-height: 44px;
  padding-block: var(--mf-space-3);
  color: var(--mf-color-link);
  cursor: pointer;
  overflow-wrap: anywhere;
}
.worksheet-totals {
  display: grid;
  gap: var(--mf-space-2);
  padding-block: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-border);
}
.worksheet-totals > p:first-child {
  font-size: var(--mf-text-lg);
}
.v-alert p {
  margin-block: var(--mf-space-2) 0;
  overflow-wrap: anywhere;
}
</style>
