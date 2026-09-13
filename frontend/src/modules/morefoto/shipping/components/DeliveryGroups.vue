<script setup lang="ts">
import { packCount, printCountLabel } from '../display';
import type { DeliveryGroup } from '../types';
import { formatMoment } from '../../handoff/display';
defineProps<{ groups: DeliveryGroup[]; staff: boolean }>();
const emit = defineEmits<{ action: [kind: 'ready' | 'unready', item: DeliveryGroup] }>();
</script>
<template>
  <section aria-labelledby="delivery-groups-title">
    <h2 id="delivery-groups-title">Состояние групп</h2>
    <p class="mf-muted mt-2 mb-5">Каждый заказ упакован отдельно. Пакеты сотрудников передаются вместе с остальными заказами учреждения.</p>
    <p v-if="!groups.length" class="delivery-empty">По выбранным условиям групп нет.</p>
    <div class="delivery-grid">
      <article v-for="g in groups" :key="g.id" class="mf-panel delivery-card" :data-testid="'delivery-group-' + g.id">
        <p class="mf-eyebrow">{{ g.institutionName }}</p>
        <h3 class="mt-2">{{ g.name }}</h3>
        <p class="delivery-muted">
          {{ g.shootName }}<span v-if="g.kind === 'staff' && g.name !== 'Сотрудники'"> · Группа сотрудников</span>
        </p>
        <v-chip class="mt-4" :color="g.overdue ? 'warning' : g.status === 'Передано в учреждение' ? 'success' : 'primary'">{{
          g.status
        }}</v-chip>
        <p :class="g.overdue ? 'delivery-late' : ''">
          {{ g.overdue ? 'Срок доставки прошёл · ' : ''
          }}{{ g.deadline ? 'Доставить до ' + formatMoment(g.deadline) : 'Срок появится после передачи ссылки группе.' }}
        </p>
        <p v-if="g.jobNumber" class="delivery-muted">
          {{ g.jobNumber }} · версия {{ g.version }} · {{ packCount(g.packs) }} / {{ printCountLabel(g.prints) }} к передаче
        </p>
        <p v-if="g.readyAt" class="delivery-muted">Готовность отмечена {{ formatMoment(g.readyAt) }}</p>
        <div class="mf-actions mt-4">
          <v-btn v-if="g.canReady" color="primary" @click="emit('action', 'ready', g)">Отметить готовность</v-btn
          ><v-btn v-if="g.canUnready" variant="outlined" @click="emit('action', 'unready', g)">Снять готовность</v-btn
          ><v-btn v-if="staff" variant="text" :to="'/cabinet/production/' + g.id">Открыть комплектацию</v-btn>
        </div>
      </article>
    </div>
  </section>
</template>
