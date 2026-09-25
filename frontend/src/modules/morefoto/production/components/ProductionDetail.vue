<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { composeVersion, newCount, printCount } from '../rules';
import { exportProduction } from '../service';
import { productionState } from '../display';
import type { ProductionGroup, ProductionCommand } from '../types';
import { formatMoment } from '../../handoff/display';
import PrintRows from './PrintRows.vue';
import OrderPackages from './OrderPackages.vue';
import MfStatus from '@/components/status/MfStatus.vue';
const props = defineProps<{ item: ProductionGroup; editable: boolean }>();
const emit = defineEmits<{ action: [kind: ProductionCommand['kind'], orderId?: string, packed?: boolean] }>();
const auth = useAuthStore(),
  selected = ref(props.item.job?.versions.slice(-1)[0]?.number ?? 1),
  downloading = ref(false),
  downloadError = ref(''),
  downloaded = ref('');
const latest = computed(() => props.item.job?.versions.slice(-1)[0]);
watch(
  () => latest.value?.number,
  (number) => {
    selected.value = number ?? 1;
  }
);
const version = computed(() => props.item.job?.versions.find((v) => v.number === selected.value));
const preview = computed(() => composeVersion(props.item.plan, props.item.job, '', '', ''));
const stale = computed(() => !!latest.value && latest.value.plan.signature !== props.item.plan.signature);
const historical = computed(() => !!version.value && version.value.number !== latest.value?.number);
async function download() {
  if (!props.item.job || !version.value || downloading.value) return;
  downloading.value = true;
  downloadError.value = '';
  downloaded.value = '';
  try {
    const file = await exportProduction(auth.getAccessToken() ?? '', props.item.job.id, version.value.number);
    const url = URL.createObjectURL(new Blob([file.csv], { type: 'text/csv;charset=utf-8' })),
      a = document.createElement('a');
    a.href = url;
    a.download = file.name;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    downloaded.value = 'Скачан ' + file.name + '. Запуск печати не изменён.';
  } catch (e) {
    downloadError.value = e instanceof Error ? e.message : 'Не удалось скачать задание.';
  } finally {
    downloading.value = false;
  }
}
</script>
<template>
  <section class="mf-panel">
    <div class="production-heading">
      <div>
        <p class="mf-eyebrow">{{ item.institutionName }}</p>
        <h1>{{ item.group.name }} · производство</h1>
        <p class="mf-muted mt-2">{{ item.shootName }}</p>
      </div>
      <MfStatus tone="info">{{ productionState(item) }}</MfStatus>
    </div>
    <div class="production-metrics mt-5">
      <div>
        <strong>{{ printCount(item.plan.rows) }}</strong
        ><span>отпечатков в актуальном составе</span>
      </div>
      <div>
        <strong>{{ new Set(item.plan.rows.map((r) => r.orderId)).size }}</strong
        ><span>пакетов по заказам</span>
      </div>
      <div>
        <strong>{{ item.plan.excluded.length }}</strong
        ><span>заказов вне задания</span>
      </div>
    </div>
    <p class="mt-5">Закрытие группы: {{ formatMoment(item.plan.closesAt) }}</p>
    <p v-if="item.plan.deliveryAt" class="mf-muted mt-2">
      Плановый срок доставки: до {{ formatMoment(item.plan.deliveryAt) }} · 7 дней после закрытия.
    </p>
  </section>
  <v-alert v-if="!item.plan.closed" type="info" variant="tonal"
    >{{ item.group.state === 'preparing' ? 'Группа ещё готовится.' : 'Приём ещё не закрыт.' }} Состав предварительный; формирование и запуск
    станут доступны после закрытия группы.</v-alert
  >
  <v-alert v-if="stale" type="warning" variant="tonal"
    >Состав или срок закрытия изменились. Сверьте актуальные позиции и сформируйте новую версию. Ранее учтённая печать сохранена.</v-alert
  >
  <section class="mf-panel">
    <div class="production-heading">
      <h2>{{ item.job ? item.job.number : 'Новое печатное задание' }}</h2>
      <v-select
        v-if="item.job"
        v-model="selected"
        class="production-version"
        label="Версия задания"
        aria-label="Версия задания"
        :items="item.job.versions.map((v) => ({ title: 'Версия ' + v.number, value: v.number }))"
        hide-details
      />
    </div>
    <template v-if="version"
      ><p class="mt-3">{{ formatMoment(version.createdAt) }} · {{ version.actor }}</p>
      <p class="mf-muted mt-2">{{ version.reason }}</p>
      <p class="mt-3">
        <strong>{{ newCount(version) }}</strong> новых отпечатков в этой версии · {{ printCount(version.plan.rows) }} всего в составе.
      </p>
      <p v-if="version.startedAt" class="mt-2">Запуск учтён {{ formatMoment(version.startedAt) }} · {{ version.startedBy }}</p>
      <p v-else class="mt-2">Задание подготовлено. Запуск ещё не учтён.</p></template
    >
    <p v-else class="mf-muted mt-3">Сохраните проверенный состав, чтобы получить номер задания и первую версию.</p>
    <v-alert v-if="historical" class="mt-4" type="info" variant="tonal">Архивная версия: только просмотр и скачивание для сверки.</v-alert>
    <div class="mf-actions mt-5">
      <v-btn
        v-if="editable && (!item.job || stale)"
        :disabled="!item.plan.closed || (!item.job && !item.plan.rows.length)"
        @click="emit('action', 'version')"
        >{{ item.job ? 'Сформировать новую версию' : 'Сформировать задание' }}</v-btn
      >
      <v-btn
        v-if="editable && version && !version.startedAt && !historical && !stale"
        :disabled="!item.plan.closed"
        @click="emit('action', 'start')"
        >Учесть запуск печати</v-btn
      >
      <v-btn v-if="version" variant="outlined" :loading="downloading" :disabled="downloading" @click="download"
        >Скачать CSV версии {{ version.number }}</v-btn
      >
    </div>
    <p v-if="downloaded" role="status" class="mt-3">{{ downloaded }}</p>
    <v-alert v-if="downloadError" type="error" variant="tonal" class="mt-3" role="alert"
      >{{ downloadError }}<v-btn variant="text" @click="download">Повторить скачивание</v-btn></v-alert
    >
    <p class="mf-muted mt-4">
      CSV содержит строки этой версии и количество к новой печати. Повторное скачивание и открытие задания не создают новый запуск.
    </p>
  </section>
  <template v-if="version"
    ><PrintRows :rows="version.runRows" />
    <section v-if="version.surplus.length" class="mf-panel">
      <h2>Ранее запущено сверх текущего состава</h2>
      <p class="mf-muted mt-3">Отложите эти отпечатки при комплектации. История их выпуска сохранена.</p>
      <p v-for="row in version.surplus" :key="row.key" class="mt-3">
        {{ row.orderNumber }} · {{ row.photoCode }} · {{ row.format }}: {{ row.next }} отпечатков
      </p>
    </section>
    <OrderPackages
      :version="version"
      :editable="editable && !historical && !stale && item.plan.closed && !version.ready"
      @pack="(id, packed) => emit('action', 'pack', id, packed)"
  /></template>
  <section v-if="!version || stale" class="production-stack">
    <div>
      <h2>Актуальный состав для следующего задания</h2>
      <p class="mf-muted mt-2">К новой печати: {{ newCount(preview) }}. Электронных позиций вне печати: {{ item.plan.digitalCount }}.</p>
    </div>
    <PrintRows :rows="preview.runRows" />
  </section>
  <section class="mf-panel">
    <h2>Готовность и доставка</h2>
    <p class="mf-muted mt-3">После сверки всех пакетов организатор подтверждает готовность и записывает передачу в учреждение.</p>
    <p v-if="latest?.ready" class="mt-3">Готовность текущей версии уже отмечена. Изменение комплектации доступно после её снятия.</p>
    <v-btn
      class="mt-4"
      variant="outlined"
      :to="{ path: '/cabinet/delivery', query: { institution: item.group.institutionId, shoot: item.group.shootId } }"
      >Перейти к доставке</v-btn
    >
  </section>
  <section class="mf-panel">
    <h2>Заказы вне задания</h2>
    <p class="mf-muted mt-3">В печать включаются только оплаченные физические позиции без удержаний и нерешённых исключений.</p>
    <p v-if="!item.plan.excluded.length" class="mt-3">
      Нерешённых исключений нет. Электронные позиции: {{ item.plan.digitalCount }} — выдаются отдельно.
    </p>
    <div v-for="excluded in item.plan.excluded" :key="excluded.orderId" class="production-package">
      <div>
        <strong>{{ excluded.number }}</strong>
        <p>{{ excluded.reason }}</p>
      </div>
      <v-btn variant="text" :to="'/cabinet/orders/' + excluded.orderId">Разобрать заказ</v-btn>
    </div>
  </section>
  <section v-if="item.job" class="mf-panel">
    <h2>История задания</h2>
    <ol class="production-history">
      <li v-for="(event, index) in [...item.job.history].reverse()" :key="index">
        <p>{{ event.text }}</p>
        <span class="mf-muted">{{ formatMoment(event.at) }} · {{ event.actor }}</span>
      </li>
    </ol>
  </section>
</template>
