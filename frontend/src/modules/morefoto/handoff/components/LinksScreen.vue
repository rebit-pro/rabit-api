<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isMockApiEnabled } from '@/mocks/config';
import { useAuthStore } from '@/stores/auth';
import AdminDialog from '../../management/components/AdminDialog.vue';
import LinkFields from './LinkFields.vue';
import { useHandoff } from '../useHandoff';
import { useHandoffEditor } from '../useHandoffEditor';
import { moscowInput } from '../rules';
import { formatMoment, problemText } from '../display';
import { loadLinks } from '../service';
import { linkError, linksApi, toLinkGroup } from '../links-api';
import { isStaffRole } from '../../types';
import type { LinkCommand, LinkEvent, LinkGroup } from '../types';
import '../handoff.css';
import MfStatus from '@/components/status/MfStatus.vue';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import { countdown, plural } from '@/components/viz/measures';
import { linkStatus } from '../../ui/statusTone';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import { clampTablePage, sortTableRows } from '../../ui/table-values';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import { galleryPath, type GalleryStepKey } from '../galleryPath';
const live = !isMockApiEnabled,
  authStore = useAuthStore(),
  route = useRoute(),
  router = useRouter(),
  // An unknown role only reads: buttons still follow the server-confirmed role of the session.
  role = () => (isStaffRole(authStore.user?.role) ? authStore.user!.role : 'head'),
  { data, loading, error, reload } = useHandoff((token) => loadLinks(token, role())),
  notice = shallowRef(''),
  failure = shallowRef(''),
  // Links the clipboard refused: the organizer copies them by hand.
  manualCopy = shallowRef('');
/** Where the organizer fixes a step of the path to the gallery (INF-04); the check and the transmission are on this screen. */
function stepFix(group: LinkGroup, key: GalleryStepKey): { to: string; label: string } | null {
  if (!live || data.value?.role !== 'organizer') return null;
  const shoot = '/cabinet/institutions/' + encodeURIComponent(group.institutionId) + '/shoots/' + encodeURIComponent(group.shootId);
  const query = '?group=' + encodeURIComponent(group.id);
  if (key === 'photos' || key === 'assign') return { to: shoot + '/photos' + query, label: 'К фотографиям' };
  if (key === 'conditions') return { to: shoot + '/conditions' + query, label: 'К условиям' };
  if (key === 'staff') return { to: '/cabinet/staff-requests?shoot=' + encodeURIComponent(group.shootId), label: 'К спискам' };
  return null;
}
const editor = useHandoffEditor(() => {
  failure.value = '';
  notice.value = 'Изменения сохранены.';
  void reload();
}, reload);
const { command, busy, errors, restored, stale, error: saveError } = editor;
// A link from another screen (the institution page, a group card) narrows the list to one group or one shoot.
const routeGroup = computed(() => (typeof route.query.group === 'string' ? route.query.group : ''));
const routeShoot = computed(() => (typeof route.query.shoot === 'string' ? route.query.shoot : ''));
function showAll(): void {
  void router.replace({ query: { ...route.query, group: undefined, shoot: undefined } });
}
const groups = computed(
  () =>
    data.value?.groups.filter(
      (g) => (!routeGroup.value || g.id === routeGroup.value) && (!routeShoot.value || g.shootId === routeShoot.value)
    ) ?? []
);
const current = computed(() =>
  command.value?.kind === 'link' ? data.value?.groups.find((g) => g.id === (command.value as LinkCommand).groupId) : undefined
);
const titles = { prepare: 'Проверить ссылку', transmit: 'Отметить передачу ссылки', correct: 'Исправить дату передачи' };
function open(group: LinkGroup, action: LinkCommand['action']) {
  editor.open(
    () => {
      const fresh = data.value!.groups.find((g) => g.id === group.id)!;
      return {
        kind: 'link',
        action,
        requestId: crypto.randomUUID(),
        groupId: fresh.id,
        revision: fresh.revision,
        signature: fresh.signature,
        sentAt: moscowInput(fresh.sentAt ?? data.value!.now),
        reason: '',
        confirmed: false,
        photosReviewed: false,
        conditionsReviewed: false,
        staffReviewed: false
      };
    },
    group.id + ':' + action
  );
}
function change(value: Partial<LinkCommand>) {
  if (command.value?.kind === 'link') Object.assign(command.value, value);
}
const tiles = [
  { text: 'Требует проверки', icon: 'mdi-alert-circle-outline', pastel: 4 },
  { text: 'Готова к передаче', icon: 'mdi-send-check-outline', pastel: 3 },
  { text: 'Приём открыт', icon: 'mdi-cart-outline', pastel: 2 },
  { text: 'Приём завершён', icon: 'mdi-check-all', pastel: 7 }
] as const;
type LinkState = (typeof tiles)[number]['text'];
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Группа', sortable: true, primary: true, width: '28%' },
  { key: 'status', label: 'Состояние', sortable: true, mobile: true, width: '19%' },
  { key: 'sentAt', label: 'Передана', type: 'date', sortable: true, mobile: true, width: '11%' },
  { key: 'closesAt', label: 'Приём до', type: 'date', sortable: true, mobile: true, width: '14%' },
  { key: 'deliveryAt', label: 'Доставка', type: 'date', sortable: true, width: '11%' }
];
const stateFilter = shallowRef<LinkState | null>(null);
const institutionFilter = shallowRef<string | null>(null);
const query = shallowRef('');
const sort = shallowRef<UiTableSort>({ key: 'closesAt', direction: 'asc' });
const page = shallowRef(1);
const pageSize = shallowRef(25);
const selected = shallowRef<string[]>([]);
const byId = computed(() => new Map((data.value?.groups ?? []).map((g) => [g.id, g])));
const counts = computed(() => {
  const result: Record<LinkState, number> = { 'Требует проверки': 0, 'Готова к передаче': 0, 'Приём открыт': 0, 'Приём завершён': 0 };
  for (const g of groups.value) result[linkStatus(g).text as LinkState]++;
  return result;
});
const institutionName = computed(() => new Map((data.value?.scope.institutions ?? []).map((i) => [i.id, i.name])));
const institutionItems = computed(() => [
  { title: 'Все учреждения', value: null },
  ...(data.value?.scope.institutions ?? []).map((i) => ({ title: i.name, value: i.id }))
]);
const stateItems = [{ title: 'Все состояния', value: null }, ...tiles.map((tile) => ({ title: tile.text, value: tile.text }))];
const filtered = computed(() => !!(stateFilter.value || institutionFilter.value || query.value.trim()));
const rows = computed<UiTableRow[]>(() => {
  const needle = query.value.trim().toLocaleLowerCase('ru');
  return groups.value
    .filter(
      (g) =>
        (!stateFilter.value || linkStatus(g).text === stateFilter.value) &&
        (!institutionFilter.value || g.institutionId === institutionFilter.value) &&
        (g.name + ' ' + g.shootName).toLocaleLowerCase('ru').includes(needle)
    )
    .map((g) => ({ id: g.id, name: g.name, status: linkStatus(g).text, sentAt: g.sentAt, closesAt: g.closesAt, deliveryAt: g.deliveryAt }));
});
const pageRows = computed(() =>
  sortTableRows(rows.value, columns, sort.value).slice((page.value - 1) * pageSize.value, page.value * pageSize.value)
);
watch([stateFilter, institutionFilter, query, pageSize, sort, routeGroup, routeShoot], () => {
  page.value = 1;
});
watch(rows, (list) => {
  page.value = clampTablePage(page.value, list.length, pageSize.value);
  const ids = new Set(list.map((row) => row.id));
  selected.value = selected.value.filter((id) => ids.has(id));
});
function group(id: string): LinkGroup {
  return byId.value.get(id)!;
}
/** What stands before the gallery: live readiness codes become steps, demo problems are already sentences. */
function stateNote(g: LinkGroup): { text: string; hint: string } | null {
  if (g.sentAt) return null;
  if (!live && g.problems.length) return { text: g.problems.map(problemText).join(' '), hint: '' };
  const steps = galleryPath(g);
  const index = steps.findIndex((step) => step.state === 'current');
  if (index < 0) return null;
  const step = steps[index]!;
  return { text: 'шаг ' + (index + 1) + ' из ' + steps.length + ': ' + step.title.toLowerCase(), hint: step.hint };
}
function left(g: LinkGroup): string {
  if (g.state === 'closed' || !data.value) return '';
  const result = countdown(g.sentAt, g.closesAt, data.value.now);
  return result.days !== null && result.days > 0 ? result.label : '';
}
const shortDate = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', timeZone: 'Europe/Moscow' });
function day(value: string | null): string {
  return value ? shortDate.format(new Date(value)) : '—';
}
const canCopy = (g: LinkGroup) => !live || g.prepared || !!g.sentAt;
const canCheck = (g: LinkGroup) => data.value?.role === 'organizer' && !g.sentAt && !g.prepared;
const canTransmit = (g: LinkGroup) => data.value?.role !== 'head' && !g.sentAt && g.prepared;
const canCorrect = (g: LinkGroup) => !!g.sentAt && ['curator', 'organizer'].includes(data.value?.role ?? '');
const hasHistory = (g: LinkGroup) => live || g.history.length > 0;
/** The page where the organizer fixes the current step; after the transmission — the photos of the group. */
function fix(g: LinkGroup): { to: string; label: string } | null {
  const step = g.sentAt ? undefined : galleryPath(g).find((item) => item.state === 'current');
  return (step && stepFix(g, step.key)) || stepFix(g, 'photos');
}
const hasMenu = (g: LinkGroup) => (!g.sentAt && canCopy(g)) || canCorrect(g) || hasHistory(g) || !!fix(g);
const url = (token: string) => window.location.origin + '/g/' + token;
/** Live keys are requested per group on demand: the list never carries gallery tokens. */
async function token(group: LinkGroup): Promise<string> {
  return live ? ((await linksApi.detail(group.id)).galleryToken ?? '') : group.galleryToken;
}
function clearNotices(): void {
  notice.value = '';
  failure.value = '';
  manualCopy.value = '';
}
async function copy(group: LinkGroup) {
  let link = '';
  clearNotices();
  try {
    link = await token(group);
    if (!link) {
      failure.value = 'Ссылка появится после проверки группы.';
      return;
    }
    await navigator.clipboard.writeText(url(link));
    notice.value = 'Ссылка скопирована. Дата передачи не изменена.';
  } catch (cause) {
    failure.value = link ? 'Скопируйте ссылку вручную: ' + url(link) : linkError(cause);
  }
}
const copying = shallowRef(false);
/** Lines «Группа — ссылка» for a message to parents; a group without a link yet is named and skipped. */
async function copySelected() {
  clearNotices();
  copying.value = true;
  const lines: string[] = [];
  const skipped: string[] = [];
  try {
    for (const id of selected.value) {
      const g = byId.value.get(id);
      if (!g) continue;
      try {
        const link = canCopy(g) ? await token(g) : '';
        if (link) lines.push(g.name + ' — ' + url(link));
        else skipped.push(g.name);
      } catch (cause) {
        skipped.push(g.name + ' (' + linkError(cause) + ')');
      }
    }
    const tail = skipped.length ? ' Без ссылки: ' + skipped.join(', ') + '.' : '';
    if (!lines.length) {
      failure.value = 'У выбранных групп ещё нет ссылок: они появятся после проверки.' + tail;
      return;
    }
    try {
      await navigator.clipboard.writeText(lines.join('\n'));
      notice.value = 'Скопировано ссылок: ' + lines.length + '. Даты передачи не изменены.' + tail;
    } catch {
      failure.value = 'Буфер обмена недоступен. Скопируйте ссылки вручную.' + tail;
      manualCopy.value = lines.join('\n');
    }
  } finally {
    copying.value = false;
  }
}
async function openGallery(group: LinkGroup) {
  const tab = window.open('', '_blank');
  try {
    const link = await token(group);
    if (!link) throw new Error('Ссылка появится после проверки группы.');
    if (tab) {
      tab.opener = null;
      tab.location.href = url(link);
    }
  } catch (cause) {
    tab?.close();
    failure.value = linkError(cause);
  }
}
const historyGroup = shallowRef<LinkGroup | null>(null);
const historyEvents = shallowRef<LinkEvent[]>([]);
const historyLink = shallowRef('');
const historyLoading = shallowRef(false);
const historyError = shallowRef('');
const showHistory = computed({
  get: () => !!historyGroup.value,
  set: (open: boolean) => {
    if (!open) historyGroup.value = null;
  }
});
/** Live history and the key come from the group detail; the demo keeps both in the list. */
async function openHistory(group: LinkGroup) {
  historyGroup.value = group;
  historyError.value = '';
  historyEvents.value = group.history;
  historyLink.value = !live && group.galleryToken ? url(group.galleryToken) : '';
  if (!live) return;
  historyLoading.value = true;
  try {
    const detail = await linksApi.detail(group.id);
    if (historyGroup.value?.id !== group.id) return;
    historyEvents.value = toLinkGroup(detail, detail).history;
    historyLink.value = detail.galleryToken ? url(detail.galleryToken) : '';
  } catch (cause) {
    if (historyGroup.value?.id === group.id) historyError.value = linkError(cause);
  } finally {
    historyLoading.value = false;
  }
}
</script>
<template>
  <header class="handoff-heading">
    <div>
      <p class="mf-eyebrow">ПЕРЕДАЧА РОДИТЕЛЯМ</p>
      <h1>Ссылки и сроки</h1>
      <p class="mf-muted">Семь дней на заказ — с фактической передачи ссылки.</p>
      <MfStatus v-if="data?.role === 'head'" tone="neutral" icon="mdi-eye-outline" class="mt-3">Только просмотр</MfStatus>
    </div>
    <v-btn variant="outlined" :loading="loading" @click="reload">Обновить</v-btn>
  </header>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
  <v-alert v-if="failure" type="error" variant="tonal" role="alert" class="mb-5">
    {{ failure }}
    <v-textarea
      v-if="manualCopy"
      :model-value="manualCopy"
      label="Ссылки выбранных групп"
      readonly
      auto-grow
      rows="2"
      hide-details
      class="mt-3"
      @focus="($event.target as HTMLTextAreaElement).select()"
    />
  </v-alert>
  <v-alert v-if="error" type="error" variant="tonal">{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert>
  <template v-if="data">
    <section class="links-tiles mb-5" aria-label="Группы по состоянию ссылки" data-testid="links-tiles">
      <MfStatTile
        v-for="tile in tiles"
        :key="tile.text"
        :label="tile.text"
        :value="counts[tile.text]"
        :unit="plural(counts[tile.text], ['группа', 'группы', 'групп'])"
        :icon="tile.icon"
        :pastel="tile.pastel"
        selectable
        :active="stateFilter === tile.text"
        @select="stateFilter = stateFilter === tile.text ? null : tile.text"
      />
    </section>
    <form class="links-filters mb-4" aria-label="Фильтры ссылок" @submit.prevent>
      <v-text-field
        :model-value="query"
        label="Группа или съёмка"
        aria-label="Группа или съёмка"
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        density="compact"
        @update:model-value="query = $event ?? ''"
      />
      <v-select
        v-model="institutionFilter"
        :items="institutionItems"
        label="Учреждение"
        aria-label="Учреждение"
        hide-details
        density="compact"
      />
      <v-select v-model="stateFilter" :items="stateItems" label="Состояние" aria-label="Состояние" hide-details density="compact" />
    </form>
    <p v-if="routeGroup || routeShoot" class="links-scope mb-3">
      {{ routeGroup ? 'Показана одна группа.' : 'Показаны группы одной съёмки.' }}
      <v-btn variant="text" density="compact" @click="showAll">Показать все группы</v-btn>
    </p>
    <UiDataTable
      title="Ссылки групп"
      label-key="name"
      density="compact"
      actions-width="176px"
      :columns="columns"
      :rows="pageRows"
      :total="rows.length"
      :page="page"
      :page-size="pageSize"
      :sort="sort"
      :selected="selected"
      :empty-title="filtered || routeGroup || routeShoot ? 'Нет групп для выбранного фильтра' : 'Групп пока нет'"
      :empty-description="filtered ? 'Измените или очистите фильтры.' : 'Ссылка появляется вместе с группой в учреждении.'"
      @sort="sort = $event"
      @page="page = $event"
      @page-size="pageSize = $event"
      @select="selected = $event"
    >
      <template #selection>
        <div class="links-bulk" data-testid="links-bulk">
          <p>Выбрано: {{ selected.length }}</p>
          <v-btn variant="outlined" density="compact" prepend-icon="mdi-content-copy" :loading="copying" @click="copySelected"
            >Скопировать ссылки</v-btn
          >
          <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
        </div>
      </template>
      <template #cell-name="{ row }">
        <span class="links-group">
          <strong>{{ row.name }}</strong>
          <small :title="institutionName.get(group(row.id).institutionId) + ' · ' + group(row.id).shootName"
            >{{ institutionName.get(group(row.id).institutionId) }} · {{ group(row.id).shootName }}</small
          >
        </span>
      </template>
      <template #cell-status="{ row }">
        <span class="links-state">
          <MfStatus :tone="linkStatus(group(row.id)).tone">{{ row.status }}</MfStatus>
          <small v-if="stateNote(group(row.id))" :title="stateNote(group(row.id))!.hint || undefined">{{
            stateNote(group(row.id))!.text
          }}</small>
        </span>
      </template>
      <template #cell-sentAt="{ row }">{{ day(group(row.id).sentAt) }}</template>
      <template #cell-closesAt="{ row }">
        <span class="links-state">
          {{ day(group(row.id).closesAt) }}
          <small v-if="left(group(row.id))" class="links-left">{{ left(group(row.id)) }}</small>
        </span>
      </template>
      <template #cell-deliveryAt="{ row }">{{ day(group(row.id).deliveryAt) }}</template>
      <template #actions="{ row }">
        <div class="links-actions">
          <template v-if="group(row.id).sentAt">
            <v-btn
              v-tooltip="'Копировать ссылку'"
              icon="mdi-content-copy"
              variant="text"
              density="compact"
              :aria-label="'Копировать ссылку: ' + row.name"
              @click="copy(group(row.id))"
            />
            <v-btn
              v-tooltip="'Открыть галерею'"
              icon="mdi-open-in-new"
              variant="text"
              density="compact"
              :aria-label="'Открыть галерею: ' + row.name"
              @click="openGallery(group(row.id))"
            />
          </template>
          <v-btn
            v-if="canCheck(group(row.id))"
            variant="tonal"
            density="compact"
            color="primary"
            :disabled="!!group(row.id).problems.length"
            :aria-label="'Проверить ссылку: ' + row.name"
            @click="open(group(row.id), 'prepare')"
            >Проверить</v-btn
          >
          <v-btn
            v-else-if="canTransmit(group(row.id))"
            variant="tonal"
            density="compact"
            color="primary"
            :aria-label="'Передать ссылку: ' + row.name"
            @click="open(group(row.id), 'transmit')"
            >Передать</v-btn
          >
          <v-menu v-if="hasMenu(group(row.id))" location="bottom end" content-class="morefoto-app mf-ui-overlay">
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" density="compact" :aria-label="'Действия: ' + row.name" />
            </template>
            <div class="links-menu">
              <template v-if="!group(row.id).sentAt && canCopy(group(row.id))">
                <v-btn variant="text" block prepend-icon="mdi-content-copy" @click="copy(group(row.id))">Копировать ссылку</v-btn>
                <v-btn variant="text" block prepend-icon="mdi-open-in-new" @click="openGallery(group(row.id))">Страница родителей</v-btn>
              </template>
              <v-btn
                v-if="canCorrect(group(row.id))"
                variant="text"
                block
                prepend-icon="mdi-calendar-edit-outline"
                @click="open(group(row.id), 'correct')"
                >Исправить дату передачи</v-btn
              >
              <v-btn v-if="hasHistory(group(row.id))" variant="text" block prepend-icon="mdi-history" @click="openHistory(group(row.id))"
                >История изменений</v-btn
              >
              <v-btn
                v-if="fix(group(row.id))"
                variant="text"
                block
                prepend-icon="mdi-image-multiple-outline"
                :to="fix(group(row.id))!.to"
                >{{ fix(group(row.id))!.label }}</v-btn
              >
            </div>
          </v-menu>
        </div>
      </template>
    </UiDataTable>
    <p class="mf-muted links-footnote mt-4">
      До отметки передачи родители видят «Фотографии ещё готовятся». Копирование ссылки не запускает срок.
    </p>
  </template>
  <v-dialog v-model="showHistory" max-width="640" aria-labelledby="links-history-title">
    <v-card v-if="historyGroup" class="morefoto-app mf-panel links-history" data-testid="links-history">
      <h2 id="links-history-title">История изменений · {{ historyGroup.name }}</h2>
      <label v-if="historyLink" class="handoff-url"
        >Ссылка группы<input
          :value="historyLink"
          readonly
          :aria-label="'Ссылка группы ' + historyGroup.name"
          @focus="($event.target as HTMLInputElement).select()"
      /></label>
      <v-progress-linear v-if="historyLoading" indeterminate aria-label="Загрузка истории" />
      <v-alert v-else-if="historyError" type="error" variant="tonal" role="alert">{{ historyError }}</v-alert>
      <p v-else-if="!historyEvents.length" class="mf-muted">Изменений пока не было: ссылку ещё не проверяли и не передавали.</p>
      <ol v-else class="links-history-events">
        <li v-for="(event, index) in historyEvents" :key="index">
          <strong>{{
            event.kind === 'prepared' ? 'Ссылка проверена' : event.kind === 'transmitted' ? 'Передача отмечена' : 'Дата исправлена'
          }}</strong>
          · {{ event.actorName ?? 'Сотрудник №' + event.actorId }} ·
          {{ formatMoment(event.at) }}
          <p v-if="event.sentAt">
            Передача: {{ formatMoment(event.sentAt) }} · приём до
            {{ formatMoment(event.closesAt) }}
          </p>
          <p v-if="event.previousSentAt">
            Прежняя передача: {{ formatMoment(event.previousSentAt) }} · приём до {{ formatMoment(event.previousClosesAt) }}
          </p>
          <p v-if="event.reason">{{ event.reason }}</p>
        </li>
      </ol>
      <div class="mf-actions">
        <v-btn variant="outlined" @click="showHistory = false">Закрыть</v-btn>
      </div>
    </v-card>
  </v-dialog>
  <AdminDialog
    :open="!!command"
    :title="command?.kind === 'link' ? titles[command.action] : ''"
    :save-label="command?.kind === 'link' ? titles[command.action] : ''"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    @close="editor.close"
    @reset="editor.reset"
    @save="editor.save"
    ><p v-if="stale" role="status" class="mf-muted mb-4">
      Черновик устарел: группа изменена на сервере после него. Отправьте форму без правок — если это ваша прошлая отправка, сервер вернёт её
      результат. Чтобы начать с актуальных данных группы, загрузите актуальные данные.
    </p>
    <LinkFields
      v-if="command?.kind === 'link' && current && data"
      :command="command"
      :group="current"
      :now="data.now"
      :errors="errors"
      @change="change"
  /></AdminDialog>
</template>
<style scoped>
.links-tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr));
  gap: var(--mf-space-4);
}
.links-filters {
  display: grid;
  grid-template-columns: minmax(220px, 2fr) minmax(180px, 1.4fr) minmax(170px, 1fr);
  gap: 12px;
}
.links-scope {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-2);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.links-group,
.links-state {
  display: grid;
  justify-items: start;
  gap: 2px;
  min-width: 0;
}
.links-group strong {
  font-weight: 600;
}
.links-group small {
  overflow: hidden;
  max-width: 100%;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.links-group small,
.links-state small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
}
.links-state .links-left {
  color: var(--mf-color-primary);
  font-weight: 600;
}
.links-actions,
.links-bulk {
  display: flex;
  align-items: center;
  gap: 2px;
}
.links-bulk {
  flex-wrap: wrap;
  gap: var(--mf-space-3);
}
.links-menu {
  display: grid;
  gap: var(--mf-space-1);
  min-width: 240px;
  padding: var(--mf-space-2);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface);
}
.links-menu .v-btn {
  justify-content: start;
}
.links-footnote {
  font-size: var(--mf-text-sm);
}
.links-history {
  display: grid;
  gap: 16px;
  padding: 24px;
}
.links-history-events {
  display: grid;
  gap: var(--mf-space-3);
  margin-left: var(--mf-space-5);
  line-height: 1.5;
}
@media (max-width: 760px) {
  .links-filters {
    grid-template-columns: 1fr;
  }
  .links-history {
    padding: 16px;
  }
}
</style>
