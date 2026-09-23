<script setup lang="ts">
import { computed } from 'vue';
import { countdown } from './measures';

const props = withDefaults(
  defineProps<{
    sentAt: string | null;
    closesAt: string | null;
    deliveryAt: string | null;
    /** Server time of the answer (`referenceNow`), so every card counts from the same moment. */
    now: string;
    timezone?: string;
  }>(),
  { timezone: 'Europe/Moscow' }
);

const left = computed(() => countdown(props.sentAt, props.closesAt, props.now));
const format = computed(() => new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', timeZone: props.timezone }));
const date = (value: string | null) => (null === value ? '—' : format.value.format(new Date(value)));
const moment = (value: string | null) => (null === value ? null : Date.parse(value));
/** Position of «today» and of the passed part between the sending and the delivery, 0–100 %. */
const progress = computed(() => {
  const start = moment(props.sentAt);
  const end = moment(props.deliveryAt);
  const now = Date.parse(props.now);
  if (null === start || null === end || end <= start) return null;
  return Math.min(100, Math.max(0, ((now - start) / (end - start)) * 100));
});
const closing = computed(() => {
  const start = moment(props.sentAt);
  const end = moment(props.deliveryAt);
  const close = moment(props.closesAt);
  if (null === start || null === end || null === close || end <= start) return 50;
  return Math.min(100, Math.max(0, ((close - start) / (end - start)) * 100));
});
const milestones = computed(() => [
  { key: 'sent', label: 'Ссылка передана', value: props.sentAt, at: 0 },
  { key: 'closes', label: 'Приём до', value: props.closesAt, at: closing.value },
  { key: 'delivery', label: 'Доставка до', value: props.deliveryAt, at: 100 }
]);
</script>

<template>
  <div class="mf-timeline" :class="{ 'mf-timeline--idle': null === sentAt }" data-testid="timeline">
    <div class="mf-timeline__track" aria-hidden="true">
      <span class="mf-timeline__passed" :style="{ width: (progress ?? 0) + '%' }"></span>
      <span
        v-for="milestone in milestones"
        :key="milestone.key"
        class="mf-timeline__point"
        :class="{ 'mf-timeline__point--passed': null !== progress && milestone.at <= progress }"
        :style="{ left: milestone.at + '%' }"
      ></span>
      <span v-if="null !== progress" class="mf-timeline__today" :style="{ left: progress + '%' }"></span>
    </div>
    <ol class="mf-timeline__milestones">
      <li v-for="milestone in milestones" :key="milestone.key">
        <span>{{ milestone.label }}</span>
        <time :datetime="milestone.value ?? undefined">{{ date(milestone.value) }}</time>
      </li>
    </ol>
    <p class="mf-timeline__countdown" :class="`mf-timeline__countdown--${left.tone}`" data-testid="countdown">
      <template v-if="null === left.days">Ссылка ещё не передана родителям</template>
      <template v-else><span class="mf-sr-only">До закрытия приёма: </span>{{ left.label }}</template>
    </p>
  </div>
</template>

<style scoped>
.mf-timeline {
  display: grid;
  gap: var(--mf-space-3);
}
.mf-timeline__track {
  position: relative;
  height: 10px;
  margin: 0 5px;
}
.mf-timeline__track::before,
.mf-timeline__passed {
  position: absolute;
  top: 4px;
  left: 0;
  height: 2px;
  content: '';
}
.mf-timeline__track::before {
  right: 0;
  background: var(--mf-color-chart-grid);
}
.mf-timeline__passed {
  background: var(--mf-color-chart-accent);
  transition: width var(--mf-duration-slow) ease;
}
.mf-timeline__point {
  position: absolute;
  top: 1px;
  width: 8px;
  height: 8px;
  margin-left: -4px;
  border-radius: var(--mf-radius-full);
  background: var(--mf-color-surface);
  box-shadow: inset 0 0 0 2px var(--mf-color-chart-grid);
}
.mf-timeline__point--passed {
  background: var(--mf-color-chart-accent);
  box-shadow: none;
}
.mf-timeline__today {
  position: absolute;
  top: 0;
  width: 10px;
  height: 10px;
  margin-left: -5px;
  border-radius: var(--mf-radius-full);
  background: var(--mf-color-text);
  box-shadow: var(--mf-shadow-ring-surface);
}
.mf-timeline__milestones {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--mf-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--mf-text-sm);
}
.mf-timeline__milestones li {
  display: grid;
  gap: 2px;
}
.mf-timeline__milestones li:nth-child(2) {
  text-align: center;
}
.mf-timeline__milestones li:last-child {
  text-align: right;
}
.mf-timeline__milestones span {
  color: var(--mf-color-text-secondary);
}
.mf-timeline__milestones time {
  font-weight: var(--mf-weight-medium);
}
.mf-timeline--idle .mf-timeline__milestones time {
  color: var(--mf-color-text-tertiary);
}
.mf-timeline__countdown {
  margin: 0;
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
}
.mf-timeline__countdown--neutral {
  color: var(--mf-color-text-secondary);
}
.mf-timeline__countdown--info {
  color: var(--mf-tone-info-fg);
}
.mf-timeline__countdown--warning {
  color: var(--mf-tone-warning-fg);
}
.mf-timeline__countdown--danger {
  color: var(--mf-tone-danger-fg);
}
/* On a phone the three dates stack under each other instead of squeezing into one row. */
@media (max-width: 479px) {
  .mf-timeline__milestones {
    grid-template-columns: 1fr;
  }
  .mf-timeline__milestones li,
  .mf-timeline__milestones li:nth-child(2),
  .mf-timeline__milestones li:last-child {
    grid-template-columns: 1fr auto;
    text-align: left;
  }
}
</style>
