<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import AdminDialog from '../../management/components/AdminDialog.vue';
import RequestFields from './RequestFields.vue';
import RequestReview from './RequestReview.vue';
import { useHandoff } from '../useHandoff';
import { useHandoffEditor } from '../useHandoffEditor';
import { useTransferPreview } from '../useTransferPreview';
import { formatMoment, requestStatus } from '../display';
import type { StaffCommand, StaffRequest } from '../types';
import '../handoff.css';
import MfStatus from '@/components/status/MfStatus.vue';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import { plural } from '@/components/viz/measures';
import { CHART_CATEGORY } from '../../ui/chartPalette';
import { toneOf } from '@/components/status/tones';
import { staffRequestTone } from '../../ui/statusTone';
const route = useRoute(),
  { auth, data, loading, error, reload } = useHandoff(),
  notice = shallowRef('');
const editor = useHandoffEditor(() => {
  notice.value = 'Изменения сохранены.';
  void reload();
});
// «Передан → Проверка → Перенесён»: where the list is now and whose move it is.
const steps = computed(() => {
  const status = selected.value?.status;
  return [
    { key: 'submitted', label: 'Передан куратору', state: 'done' },
    {
      key: 'review',
      label: status === 'clarification' ? 'Нужно уточнение' : 'Проверка куратором',
      state: status === 'transferred' ? 'done' : status === 'clarification' ? 'attention' : 'current'
    },
    { key: 'transferred', label: 'Наборы перенесены', state: status === 'transferred' ? 'done' : 'todo' }
  ];
});
const { command, busy, errors, restored, error: saveError } = editor;
const selected = computed(() => data.value?.requests.find((r) => r.id === route.params.requestId));
const reviewing = computed(() => ['curator', 'organizer'].includes(data.value?.role ?? ''));
const createAllowed = computed(() => ['teacher', 'organizer'].includes(data.value?.role ?? ''));
const titles = { submit: 'Передать список куратору', clarify: 'Запросить уточнение', confirm: 'Подтвердить перенос' };
type RequestState = keyof typeof requestStatus;
const statusFilter = shallowRef<RequestState | null>(null);
const requests = computed(
  () =>
    data.value?.requests
      .filter((r) => !route.query.shoot || r.shootId === route.query.shoot)
      .filter((r) => null === statusFilter.value || r.status === statusFilter.value)
      .slice()
      .reverse() ?? []
);
// Live counts come from the server summary (U5); the demo keeps every list locally and counts it.
const statusCounts = computed(() => {
  if (!data.value) return null;
  if (data.value.requestSummary) return data.value.requestSummary;
  const counts: Record<RequestState, number> = { submitted: 0, clarification: 0, transferred: 0 };
  for (const request of data.value.requests) counts[request.status as RequestState] += 1;
  return counts;
});
const statusIcons: Record<RequestState, string> = {
  submitted: 'mdi-clipboard-text-clock-outline',
  clarification: 'mdi-message-question-outline',
  transferred: 'mdi-check-circle-outline'
};
const lists = ['список', 'списка', 'списков'] as const;
function toggleStatus(status: RequestState): void {
  statusFilter.value = statusFilter.value === status ? null : status;
}
const { preview, error: previewError } = useTransferPreview(data, selected, reviewing);
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
        signature: action === 'confirm' && current ? (preview.value?.signature ?? '') : ''
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
      <h1>{{ route.params.requestId ? 'Заявка на список сотрудников' : 'Заявки на списки сотрудников' }}</h1>
      <p class="mf-muted">Проверка детей сотрудников и перенос полных наборов.</p>
      <MfStatus v-if="data?.role === 'head'" tone="neutral" icon="mdi-eye-outline" class="mt-3">Только просмотр</MfStatus>
    </div>
    <v-btn v-if="!route.params.requestId && createAllowed" @click="open('submit')">Новый список</v-btn>
  </header>
  <v-alert v-if="notice" type="success" variant="tonal" role="status" class="mb-5">{{ notice }}</v-alert>
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
          <MfStatus :tone="toneOf(staffRequestTone, selected.status)">{{ requestStatus[selected.status] }}</MfStatus>
        </header>
        <ol class="request-steps" aria-label="Ход проверки списка">
          <li v-for="step in steps" :key="step.key" :class="'request-steps__step--' + step.state">
            <v-icon
              :icon="step.state === 'done' ? 'mdi-check-circle' : step.state === 'attention' ? 'mdi-alert-circle-outline' : 'mdi-circle'"
              size="18"
              aria-hidden="true"
            />
            <span>{{ step.label }}</span>
            <span class="mf-sr-only">{{ step.state === 'done' ? '— выполнено' : step.state === 'todo' ? '— впереди' : '— сейчас' }}</span>
          </li>
        </ol>
        <v-alert v-if="selected.staffEligibility?.eligible" type="success" variant="tonal" class="mb-5" data-testid="staff-eligibility">
          Право сотрудника подтверждено сервером · {{ formatMoment(selected.staffEligibility.verifiedAt) }}
        </v-alert>
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
        <details class="handoff-history">
          <summary>История списка · {{ selected.history.length }}</summary>
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
      <section v-if="statusCounts && reviewing" class="handoff-tiles" aria-label="Списки по статусу" data-testid="request-tiles">
        <MfStatTile
          v-for="(label, status) in requestStatus"
          :key="status"
          :label="label"
          :value="statusCounts[status]"
          :unit="plural(statusCounts[status], lists)"
          :pastel="CHART_CATEGORY.requests"
          :icon="statusIcons[status]"
          selectable
          :active="statusFilter === status"
          @select="toggleStatus(status)"
        />
      </section>
      <p v-else-if="statusCounts" class="handoff-pills" aria-label="Мои списки по статусу">
        <MfStatus v-for="(label, status) in requestStatus" :key="status" :tone="toneOf(staffRequestTone, status)"
          >{{ label }}: {{ statusCounts[status] }}</MfStatus
        >
      </p>
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
          <MfStatus :tone="toneOf(staffRequestTone, request.status)">{{ requestStatus[request.status] }}</MfStatus>
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
