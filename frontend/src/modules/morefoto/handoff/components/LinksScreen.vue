<script setup lang="ts">
import { computed, shallowRef } from 'vue';
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
import MfTimeline from '@/components/viz/MfTimeline.vue';
import { linkStatus } from '../../ui/statusTone';
const live = !isMockApiEnabled,
  authStore = useAuthStore(),
  route = useRoute(),
  router = useRouter(),
  // An unknown role only reads: buttons still follow the server-confirmed role of the session.
  role = () => (isStaffRole(authStore.user?.role) ? authStore.user!.role : 'head'),
  { data, loading, error, reload } = useHandoff((token) => loadLinks(token, role())),
  notice = shallowRef(''),
  failure = shallowRef(''),
  histories = shallowRef<Record<string, LinkEvent[]>>({});
/** Where the organizer fixes what keeps a group from its link (INF-04): the tab of the shoot or the staff lists. */
function problemFix(group: LinkGroup, problem: string): { to: string; label: string } | null {
  if (!live || data.value?.role !== 'organizer') return null;
  const shoot = '/cabinet/institutions/' + encodeURIComponent(group.institutionId) + '/shoots/' + encodeURIComponent(group.shootId);
  const query = '?group=' + encodeURIComponent(group.id);
  if (['noPhotos', 'photosProcessing', 'unassignedPhotos'].includes(problem))
    return { to: shoot + '/photos' + query, label: 'К фотографиям' };
  if (problem === 'noProducts') return { to: shoot + '/conditions' + query, label: 'К условиям' };
  if (problem === 'staffRequestsPending')
    return { to: '/cabinet/staff-requests?shoot=' + encodeURIComponent(group.shootId), label: 'К спискам' };
  return null;
}
const editor = useHandoffEditor(() => {
  failure.value = '';
  notice.value = 'Изменения сохранены.';
  void reload();
});
const { command, busy, errors, restored, error: saveError } = editor;
const filter = computed({
  get: () => (typeof route.query.group === 'string' ? route.query.group : ''),
  set: (group) => {
    void router.replace({ query: { ...route.query, group: group || undefined } });
  }
});
const groups = computed(
  () =>
    data.value?.groups.filter((g) => (!filter.value || g.id === filter.value) && (!route.query.shoot || g.shootId === route.query.shoot)) ??
    []
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
const url = (token: string) => window.location.origin + '/g/' + token;
/** Live keys are requested per group on demand: the list never carries gallery tokens. */
async function token(group: LinkGroup): Promise<string> {
  return live ? ((await linksApi.detail(group.id)).galleryToken ?? '') : group.galleryToken;
}
async function copy(group: LinkGroup) {
  let link = '';
  notice.value = '';
  failure.value = '';
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
async function history(group: LinkGroup, event: Event) {
  if (!live || !(event.target as HTMLDetailsElement).open) return;
  try {
    const detail = await linksApi.detail(group.id);
    histories.value = { ...histories.value, [group.id]: toLinkGroup(detail, detail).history };
  } catch (cause) {
    failure.value = linkError(cause);
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
  <v-alert v-if="failure" type="error" variant="tonal" role="alert" class="mb-5">{{ failure }}</v-alert>
  <v-alert v-if="error" type="error" variant="tonal">{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert>
  <template v-if="data">
    <v-select
      v-model="filter"
      label="Группа"
      aria-label="Группа"
      :items="[{ title: 'Все группы', value: '' }, ...data.groups.map((g) => ({ title: g.name + ' · ' + g.shootName, value: g.id }))]"
      class="handoff-filter"
    />
    <p v-if="!groups.length" class="handoff-empty">Нет групп для выбранного фильтра.</p>
    <article v-for="group in groups" :key="group.id" class="handoff-card" :data-testid="'link-' + group.id">
      <header>
        <div>
          <p class="mf-muted">{{ data.scope.institutions.find((i) => i.id === group.institutionId)?.name }} · {{ group.shootName }}</p>
          <h2>{{ group.name }}</h2>
        </div>
        <MfStatus :tone="linkStatus(group).tone">{{ linkStatus(group).text }}</MfStatus>
      </header>
      <MfTimeline
        class="handoff-timeline"
        :sent-at="group.sentAt"
        :closes-at="group.closesAt"
        :delivery-at="group.deliveryAt"
        :now="data.now"
      />
      <label v-if="!live" class="handoff-url"
        >Ссылка группы<input
          :value="url(group.galleryToken)"
          readonly
          :aria-label="'Ссылка группы ' + group.name"
          @focus="($event.target as HTMLInputElement).select()"
      /></label>
      <p v-if="!group.sentAt" class="mf-muted mb-2">
        {{ live && !group.prepared ? 'Ссылка появится после проверки группы.' : 'Копирование не запускает срок.' }}
      </p>
      <ul v-if="!group.sentAt && group.problems.length" class="handoff-problems" aria-label="Что мешает открыть галерею">
        <li v-for="problem in group.problems" :key="problem">
          <v-icon icon="mdi-alert-circle-outline" size="18" aria-hidden="true" />
          <span>{{ problemText(problem) }}</span>
          <RouterLink v-if="problemFix(group, problem)" :to="problemFix(group, problem)!.to">{{
            problemFix(group, problem)!.label
          }}</RouterLink>
        </li>
      </ul>
      <div class="mf-actions">
        <template v-if="!live || group.prepared">
          <v-btn variant="outlined" @click="copy(group)">Копировать ссылку</v-btn
          ><v-btn variant="text" @click="openGallery(group)">Открыть галерею</v-btn>
        </template>
        <v-btn v-if="data.role === 'organizer' && !group.sentAt" :disabled="!!group.problems.length" @click="open(group, 'prepare')"
          >Проверить ссылку</v-btn
        >
        <v-btn v-if="data.role !== 'head' && !group.sentAt && group.prepared" @click="open(group, 'transmit')">Отметить передачу</v-btn>
        <v-btn v-if="['curator', 'organizer'].includes(data.role) && group.sentAt" variant="text" @click="open(group, 'correct')"
          >Исправить дату</v-btn
        >
      </div>
      <details v-if="live || group.history.length" class="handoff-history" @toggle="history(group, $event)">
        <summary>История изменений{{ live ? '' : ' · ' + group.history.length }}</summary>
        <ol>
          <li v-for="(event, index) in histories[group.id] ?? group.history" :key="index">
            <strong>{{
              event.kind === 'prepared' ? 'Ссылка проверена' : event.kind === 'transmitted' ? 'Передача отмечена' : 'Дата исправлена'
            }}</strong>
            · {{ event.actorName ?? 'Сотрудник №' + event.actorId }} · {{ formatMoment(event.at) }}
            <p v-if="event.sentAt">Передача: {{ formatMoment(event.sentAt) }} · приём до {{ formatMoment(event.closesAt) }}</p>
            <p v-if="event.previousSentAt">
              Прежняя передача: {{ formatMoment(event.previousSentAt) }} · приём до {{ formatMoment(event.previousClosesAt) }}
            </p>
            <p v-if="event.reason">{{ event.reason }}</p>
          </li>
        </ol>
      </details>
    </article>
  </template>
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
    ><LinkFields
      v-if="command?.kind === 'link' && current && data"
      :command="command"
      :group="current"
      :now="data.now"
      :errors="errors"
      @change="change"
  /></AdminDialog>
</template>
