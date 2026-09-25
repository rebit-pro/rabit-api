<script setup lang="ts">
import { computed } from 'vue';
import type { StatusTone } from '../status/tones';
import { countLabel, percent } from './measures';

export interface DistributionSegment {
  key: string;
  label: string;
  value: number;
  /** A status split uses its tone; a category split uses its pastel (chartPalette). */
  tone?: StatusTone;
  pastel?: number;
}

const props = withDefaults(
  defineProps<{
    title: string;
    segments: DistributionSegment[];
    /** Forms of the counted noun for «из N групп». */
    unitForms: readonly [string, string, string];
  }>(),
  {}
);

const total = computed(() => props.segments.reduce((sum, segment) => sum + segment.value, 0));
const visible = computed(() => props.segments.filter((segment) => segment.value > 0));
const caption = computed(() => props.title + ' — из ' + countLabel(total.value, props.unitForms));
const fill = (segment: DistributionSegment) =>
  segment.tone
    ? { '--mf-segment-fill': `var(--mf-tone-${segment.tone}-border)`, '--mf-segment-edge': `var(--mf-tone-${segment.tone}-fg)` }
    : {
        '--mf-segment-fill': `var(--mf-chart-${segment.pastel ?? 7})`,
        '--mf-segment-edge': `var(--mf-chart-${segment.pastel ?? 7}-strong)`
      };
</script>

<template>
  <figure class="mf-distribution" data-testid="distribution">
    <figcaption class="mf-distribution__caption">
      <span class="mf-distribution__title">{{ title }}</span>
      <span class="mf-distribution__total">из {{ countLabel(total, unitForms) }}</span>
    </figcaption>
    <div class="mf-distribution__bar" aria-hidden="true">
      <span
        v-for="(segment, index) in visible"
        :key="segment.key"
        class="mf-distribution__segment"
        :class="`mf-distribution__segment--${index % 4}`"
        :style="{ flexGrow: segment.value, ...fill(segment) }"
      ></span>
      <span v-if="!total" class="mf-distribution__empty"></span>
    </div>
    <ul class="mf-distribution__legend" aria-hidden="true">
      <li v-for="segment in segments" :key="segment.key">
        <span class="mf-distribution__dot" :style="fill(segment)"></span>
        {{ segment.label }} <strong>{{ segment.value }}</strong> · {{ percent(segment.value, total) }} %
      </li>
    </ul>
    <table class="mf-sr-only">
      <caption>
        {{
          caption
        }}
      </caption>
      <tbody>
        <tr v-for="segment in segments" :key="segment.key">
          <th scope="row">{{ segment.label }}</th>
          <td>{{ segment.value }}</td>
          <td>{{ percent(segment.value, total) }} %</td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>

<style scoped>
.mf-distribution {
  display: grid;
  gap: var(--mf-space-3);
  margin: 0;
}
.mf-distribution__caption {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--mf-space-2);
}
.mf-distribution__title {
  font-weight: var(--mf-weight-semibold);
}
.mf-distribution__total {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.mf-distribution__bar {
  display: flex;
  gap: 2px;
  height: 12px;
  overflow: hidden;
  border-radius: var(--mf-radius-xs);
  background: var(--mf-color-surface);
}
.mf-distribution__segment {
  min-width: 4px;
  border-radius: 2px;
  background: var(--mf-segment-fill);
  box-shadow: inset 0 0 0 1px var(--mf-segment-edge);
}
.mf-distribution__empty {
  flex: 1;
  background: var(--mf-color-chart-track);
}
.mf-distribution__legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2) var(--mf-space-5);
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.mf-distribution__legend li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.mf-distribution__legend strong {
  color: var(--mf-color-text);
  font-variant-numeric: tabular-nums lining-nums;
}
.mf-distribution__dot {
  width: 10px;
  height: 10px;
  border-radius: var(--mf-radius-full);
  background: var(--mf-segment-fill);
  box-shadow: inset 0 0 0 1px var(--mf-segment-edge);
}
/* Colors vanish in forced-colors mode: segments keep apart by their border pattern, the legend keeps the words. */
@media (forced-colors: active) {
  .mf-distribution__segment {
    border: 2px solid CanvasText;
  }
  .mf-distribution__segment--1 {
    border-style: dashed;
  }
  .mf-distribution__segment--2 {
    border-style: dotted;
  }
  .mf-distribution__segment--3 {
    border-style: double;
  }
}
</style>
