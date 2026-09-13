<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import AdminDialog from '../../management/components/AdminDialog.vue';
import RequestFields from './RequestFields.vue';
import RequestReview from './RequestReview.vue';
import { useHandoff } from '../useHandoff';
import { useHandoffEditor } from '../useHandoffEditor';
import { reviewRequest } from '../rules';
import { formatMoment, requestStatus } from '../display';
import type { StaffCommand, StaffRequest, RequestPreview } from '../types';
import '../handoff.css';
const route = useRoute(),
  { auth, data, loading, error, reload } = useHandoff(),
  notice = shallowRef('');
const editor = useHandoffEditor(() => {
  notice.value = 'Изменения сохранены.';
  void reload();
});
const { command, busy, errors, restored, error: saveError } = editor;
const selected = computed(() => data.value?.requests.find((r) => r.id === route.params.requestId));
const reviewing = computed(() => ['curator', 'organizer'].includes(data.value?.role ?? ''));
const createAllowed = computed(() => ['teacher', 'organizer'].includes(data.value?.role ?? ''));
const titles = { submit: 'Передать список куратору', clarify: 'Запросить уточнение', confirm: 'Подтвердить перенос' };
const requests = computed(
  () =>
    data.value?.requests
      .filter((r) => !route.query.shoot || r.shootId === route.query.shoot)
      .slice()
      .reverse() ?? []
);
function previewFor(request: StaffRequest): RequestPreview {
  return reviewRequest(request, data.value!.groups, { photos: data.value!.photos, covers: {} });
}
const preview = computed(() => {
  if (!selected.value || !reviewing.value || selected.value.status !== 'submitted') return null;
  try {
    return previewFor(selected.value);
  } catch {
    return null;
  }
});
const previewError = computed(() => {
  if (!selected.value || !reviewing.value || selected.value.status !== 'submitted') return '';
  try {
    previewFor(selected.value);
    return '';
  } catch (e) {
    return e instanceof Error ? e.message : 'Набор недоступен.';
  }
});
function open(action: StaffCommand['action'], request?: StaffRequest) {
  editor.open(
    () => {
      const current = request ? data.value!.requests.find((r) => r.id === request.id) : undefined;
      const initialGroup = data.value!.groups.find((g) => g.kind === 'regular' && (!route.query.shoot || g.shootId === route.query.shoot));
      return {
        kind: 'request',
        action,
        requestId: crypto.randomUUID(),
        id: current?.id ?? null,
        revision: current?.revision ?? null,
        institutionId: current?.institutionId ?? initialGroup?.institutionId ?? '',
        shootId: current?.shootId ?? initialGroup?.shootId ?? '',
        rows: current?.rows.map((r) => ({ id: r.id, groupId: r.groupId, code: r.code })) ?? [
          { id: crypto.randomUUID(), groupId: initialGroup?.id ?? '', code: '' }
        ],
        comment: current?.comment ?? '',
        reason: '',
        confirmed: false,
        signature: action === 'confirm' && current ? previewFor(current).signature : ''
      };
    },
    (request?.id ?? 'new') + ':' + action
  );
}
function change(value: Partial<StaffCommand>) {
  if (command.value?.kind === 'request') Object.assign(command.value, value);
}
</script>
<template>
  <header class="handoff-heading">
    <div>
      <p class="mf-eyebrow">РАБОТА С УЧРЕЖДЕНИЕМ</p>
      <h1>{{ route.params.requestId ? 'Список сотрудников' : 'Списки сотрудников' }}</h1>
      <p class="mf-muted">Проверка детей сотрудников и перенос полных наборов.</p>
    </div>
    <v-btn v-if="!route.params.requestId && createAllowed" @click="open('submit')">Новый список</v-btn>
  </header>
  <p v-if="notice" role="status" class="handoff-notice">{{ notice }}</p>
  <v-alert v-if="error" type="error" variant="tonal">{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert>
  <p v-if="loading && !data" role="status">Загружаем списки…</p>
  <template v-if="data">
    <template v-if="route.params.requestId">
      <v-btn to="/cabinet/staff-requests" variant="text" prepend-icon="mdi-arrow-left">Все списки</v-btn>
      <v-alert v-if="!selected" type="warning" variant="tonal" class="mt-5">Список не найден или недоступен в вашей области.</v-alert>
      <article v-else class="handoff-card" data-testid="request-detail">
        <header>
          <div>
            <p class="mf-muted">{{ data.scope.institutions.find((i) => i.id === selected?.institutionId)?.name }}</p>
            <h2>{{ data.scope.shoots.find((s) => s.id === selected?.shootId)?.name }}</h2>
            <p class="mf-muted">
              {{ selected.createdByName ?? 'Сотрудник №' + selected.createdBy }} · передан {{ formatMoment(selected.createdAt) }}
            </p>
          </div>
          <v-chip :color="selected.status === 'transferred' ? 'success' : selected.status === 'clarification' ? 'warning' : 'primary'">{{
            requestStatus[selected.status]
          }}</v-chip>
        </header>
        <p v-if="selected.comment">{{ selected.comment }}</p>
        <div v-for="row in selected.rows" :key="row.id" class="handoff-row">
          <strong>{{ data.groups.find((g) => g.id === row.groupId)?.name }} · {{ row.childCode }}</strong>
          <p>Указанный код: {{ row.code }} · при отправке {{ row.photoIds.length }} фото</p>
          <p v-if="selected.results?.some((r) => r.rowId === row.id)">
            Перенесено {{ selected?.results?.find((r) => r.rowId === row.id)?.photoIds.length }} фото · новый код
            {{ selected?.results?.find((r) => r.rowId === row.id)?.targetChildCode }}
          </p>
        </div>
        <p v-if="previewError" role="alert" class="mb-5">{{ previewError }}</p>
        <div v-if="selected.status !== 'transferred'" class="mf-actions">
          <v-btn v-if="selected.createdBy === auth.user?.id && createAllowed" @click="open('submit', selected)">{{
            selected.status === 'clarification' ? 'Уточнить список' : 'Изменить список'
          }}</v-btn>
          <v-btn v-if="reviewing" variant="outlined" @click="open('clarify', selected)">Запросить уточнение</v-btn>
          <v-btn v-if="reviewing && selected.status === 'submitted'" :disabled="!preview" @click="open('confirm', selected)"
            >Проверить и перенести</v-btn
          >
        </div>
        <details open class="handoff-history">
          <summary>История списка</summary>
          <ol>
            <li v-for="(event, index) in selected.history" :key="index">
              <strong>{{
                event.kind === 'submitted' ? 'Список передан' : event.kind === 'clarification' ? 'Запрошено уточнение' : 'Наборы перенесены'
              }}</strong>
              · {{ event.actorName ?? 'Сотрудник №' + event.actorId }} · {{ formatMoment(event.at) }}
              <p>{{ event.comment }}</p>
            </li>
          </ol>
        </details>
      </article>
    </template>
    <template v-else>
      <p v-if="!requests.length" class="handoff-empty">
        Списков пока нет.
        {{ createAllowed ? 'Добавьте детей сотрудников по кодам из галереи.' : 'Здесь появятся списки от ответственных ваших учреждений.' }}
      </p>
      <article v-for="request in requests" :key="request.id" class="handoff-card" data-testid="staff-request">
        <header>
          <div>
            <p class="mf-muted">{{ data.scope.institutions.find((i) => i.id === request.institutionId)?.name }}</p>
            <h2>{{ data.scope.shoots.find((s) => s.id === request.shootId)?.name }}</h2>
            <p>{{ request.rows.length }} детей · {{ formatMoment(request.createdAt) }}</p>
          </div>
          <v-chip>{{ requestStatus[request.status] }}</v-chip>
        </header>
        <v-btn :to="'/cabinet/staff-requests/' + request.id" variant="outlined">Открыть список</v-btn>
      </article>
    </template>
  </template>
  <AdminDialog
    :open="!!command"
    :focus-heading="command?.kind === 'request' && command.action === 'confirm'"
    :title="command?.kind === 'request' ? titles[command.action] : ''"
    :save-label="command?.kind === 'request' ? titles[command.action] : ''"
    :busy="busy"
    :error="saveError"
    :restored="restored"
    @close="editor.close"
    @reset="editor.reset"
    @save="editor.save"
  >
    <RequestFields v-if="command?.kind === 'request' && data" :command="command" :data="data" :errors="errors" @change="change" />
    <RequestReview
      v-if="command?.kind === 'request' && command.action === 'confirm' && preview && data"
      :command="command"
      :preview="preview"
      :groups="data.groups"
      :errors="errors"
      @change="change"
    />
  </AdminDialog>
</template>
