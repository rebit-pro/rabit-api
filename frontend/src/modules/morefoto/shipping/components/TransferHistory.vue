<script setup lang="ts">
import { packCount, printCountLabel } from '../display';
import type { TransferSummary } from '../types';
import { formatMoment } from '../../handoff/display';
defineProps<{ transfers: TransferSummary[] }>();
</script>
<template>
  <section aria-labelledby="delivery-history-title">
    <h2 id="delivery-history-title">Передано в учреждение</h2>
    <p v-if="!transfers.length" class="delivery-empty mt-5">Передач ещё нет. Здесь появятся фактическая дата и получатель.</p>
    <ol v-else class="delivery-history mt-5">
      <li v-for="t in transfers" :key="t.id" class="mf-panel delivery-card" data-testid="transfer-history">
        <div class="delivery-row">
          <div>
            <h3>{{ t.number }} · {{ t.institutionName }}</h3>
            <p class="delivery-muted">{{ t.shootName }}</p>
          </div>
          <v-chip color="success">Получено учреждением</v-chip>
        </div>
        <p>
          <strong>{{ formatMoment(t.at) }}</strong>
        </p>
        <p>Ответственный: {{ t.responsible }} · Принял: {{ t.receiver }}</p>
        <p class="delivery-muted">
          Пакетов: {{ t.groups.reduce((n, g) => n + g.packs, 0) }} · Отпечатков: {{ t.groups.reduce((n, g) => n + g.prints, 0) }}
        </p>
        <details class="mt-3">
          <summary>Группы и сроки передачи</summary>
          <ul class="delivery-lines">
            <li v-for="g in t.groups" :key="g.groupId">
              <strong>{{ g.groupName }}</strong
              ><span v-if="g.kind === 'staff' && g.groupName !== 'Сотрудники'"> · Сотрудники</span>
              <p>{{ packCount(g.packs) }} · {{ printCountLabel(g.prints) }}</p>
              <p :class="g.deadline && t.at > g.deadline ? 'delivery-late' : 'delivery-muted'">
                Плановый срок: {{ formatMoment(g.deadline) }}<span v-if="g.deadline && t.at > g.deadline"> · Передано после срока</span>
              </p>
            </li>
          </ul>
          <p v-if="t.comment">{{ t.comment }}</p>
          <p class="delivery-muted">Записал {{ t.actor }} · {{ formatMoment(t.recordedAt) }}</p>
        </details>
      </li>
    </ol>
  </section>
</template>
