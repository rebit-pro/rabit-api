<script setup lang="ts">
import type { ScopeSnapshot } from '../../types';
import type { RequestSummary } from '../types';
import { formatMoment, requestStatus } from '../../handoff/display';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { staffRequestTone } from '../../ui/statusTone';
defineProps<{
  readonly requests: RequestSummary[];
  readonly scope: ScopeSnapshot;
  readonly shoot?: string;
}>();
</script>
<template>
  <section class="teacher-requests mf-panel" data-testid="teacher-requests">
    <header class="teacher-requests__heading">
      <div>
        <h2>Мои списки сотрудников</h2>
        <p class="mf-muted mt-2">Списки по показанным группам. Уточнения — в начале.</p>
      </div>
      <v-btn
        :to="{
          path: '/cabinet/staff-requests',
          query: { shoot: shoot || undefined }
        }"
        variant="outlined"
        >Списки и отправка</v-btn
      >
    </header>
    <p v-if="!requests.length" class="mf-muted mt-5">
      Списков по выбранным группам пока нет. Добавьте коды детей сотрудников и передайте список куратору.
    </p>
    <article v-for="request in requests" :key="request.id" class="teacher-requests__row" :data-testid="'request-summary-' + request.id">
      <div>
        <h3>{{ scope.shoots.find((s) => s.id === request.shootId)?.name }}</h3>
        <p class="mf-muted">
          {{ request.groupIds.map((id) => scope.groups.find((g) => g.id === id)?.name).join(', ') }}
        </p>
        <p class="mf-muted">Передан {{ formatMoment(request.createdAt) }}</p>
      </div>
      <MfStatus :tone="toneOf(staffRequestTone, request.status)">{{ requestStatus[request.status] }}</MfStatus>
      <v-btn :to="'/cabinet/staff-requests/' + request.id" variant="text">{{
        request.status === 'clarification' ? 'Уточнить список' : 'Открыть список'
      }}</v-btn>
    </article>
  </section>
</template>
<style scoped>
.teacher-requests {
  margin-top: 32px;
}
.teacher-requests__heading,
.teacher-requests__row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
}
.teacher-requests__heading > div,
.teacher-requests__row > div:first-child {
  flex: 1 1 240px;
  min-width: 0;
}
.teacher-requests__row {
  border-top: 1px solid #dde2e5;
  margin-top: 20px;
  padding-top: 20px;
}
.teacher-requests__row p {
  margin-top: 6px;
}
.teacher-requests__row h3 {
  overflow-wrap: anywhere;
}
</style>
