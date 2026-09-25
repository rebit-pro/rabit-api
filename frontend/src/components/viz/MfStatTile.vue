<script setup lang="ts">
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import type { StatusTone } from '../status/tones';

const props = withDefaults(
  defineProps<{
    /** Short caption above the number. */
    label: string;
    /** null means the value is unknown: «—» with «нет данных» instead of a fake zero. */
    value?: number | null;
    /** The word after the number, already declined by the caller: «групп». */
    unit?: string;
    hint?: string;
    hintTone?: StatusTone;
    /** Pastel of the category circle, see modules/morefoto/ui/chartPalette.ts. */
    pastel?: number;
    icon: string;
    /** A tile that opens a filtered list. */
    to?: RouteLocationRaw | null;
    /** A tile that switches a filter on the same screen; `active` marks the chosen one. */
    selectable?: boolean;
    active?: boolean;
    /** Figures that do not exist yet (finance before the payment provider): no digits at all. */
    unavailable?: boolean;
    unavailableText?: string;
    loading?: boolean;
  }>(),
  {
    value: null,
    unit: '',
    hint: '',
    hintTone: 'neutral',
    pastel: 7,
    to: null,
    selectable: false,
    active: false,
    unavailable: false,
    unavailableText: 'Недоступно до подключения оплаты',
    loading: false
  }
);
const emit = defineEmits<{ select: [] }>();

const tag = computed(() => (props.unavailable ? 'div' : props.to ? 'router-link' : props.selectable ? 'button' : 'div'));
const number = computed(() => (null === props.value ? '—' : new Intl.NumberFormat('ru-RU').format(props.value)));
const accessibleLabel = computed(() => {
  if (props.unavailable) return props.label + ': ' + props.unavailableText.toLowerCase();
  const value = null === props.value ? 'нет данных' : number.value + (props.unit ? ' ' + props.unit : '');
  return props.label + ': ' + value + (props.hint ? '. ' + props.hint : '');
});
</script>

<template>
  <component
    :is="tag"
    class="mf-stat-tile"
    :class="{
      'mf-stat-tile--interactive': !unavailable && (to || selectable),
      'mf-stat-tile--active': active,
      'mf-stat-tile--unavailable': unavailable,
      'mf-stat-tile--zero': 0 === value
    }"
    :to="tag === 'router-link' ? to : undefined"
    :type="tag === 'button' ? 'button' : undefined"
    :aria-pressed="tag === 'button' ? active : undefined"
    :aria-label="accessibleLabel"
    :style="{ '--mf-tile-bg': `var(--mf-pastel-${pastel}-bg)`, '--mf-tile-fg': `var(--mf-pastel-${pastel}-fg)` }"
    data-testid="stat-tile"
    @click="tag === 'button' && emit('select')"
  >
    <span class="mf-stat-tile__icon" aria-hidden="true"><v-icon :icon="unavailable ? 'mdi-lock-outline' : icon" size="20" /></span>
    <span class="mf-stat-tile__label" aria-hidden="true">{{ label }}</span>
    <span v-if="loading" class="mf-stat-tile__skeleton" aria-hidden="true"></span>
    <span v-else-if="unavailable" class="mf-stat-tile__unavailable" aria-hidden="true">{{ unavailableText }}</span>
    <span v-else class="mf-stat-tile__value" aria-hidden="true"
      ><strong>{{ number }}</strong
      ><span v-if="unit && null !== value" class="mf-stat-tile__unit">{{ unit }}</span
      ><span v-if="null === value" class="mf-stat-tile__unit">нет данных</span></span
    >
    <span
      v-if="hint && !unavailable && !loading"
      class="mf-stat-tile__hint"
      :class="`mf-stat-tile__hint--${hintTone}`"
      aria-hidden="true"
      >{{ hint }}</span
    >
    <slot v-if="0 === value && !loading" name="empty" />
  </component>
</template>

<style scoped>
.mf-stat-tile {
  display: grid;
  grid-template-columns: 40px 1fr;
  grid-template-areas:
    'icon label'
    'value value'
    'hint hint';
  align-items: center;
  column-gap: var(--mf-space-3);
  row-gap: var(--mf-space-2);
  min-width: 0;
  padding: var(--mf-space-5) var(--mf-space-6);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-surface);
  box-shadow: var(--mf-shadow-xs);
  color: var(--mf-color-text);
  font: inherit;
  text-align: left;
  text-decoration: none;
  transition:
    box-shadow var(--mf-duration-fast) ease,
    border-color var(--mf-duration-fast) ease;
}
.mf-stat-tile--interactive {
  cursor: pointer;
}
.mf-stat-tile--interactive:hover {
  box-shadow: var(--mf-shadow-sm);
}
.mf-stat-tile--interactive:focus-visible {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
.mf-stat-tile--active {
  border-color: var(--mf-color-primary);
  box-shadow: inset 0 0 0 1px var(--mf-color-primary);
}
.mf-stat-tile__icon {
  grid-area: icon;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--mf-radius-full);
  background: var(--mf-tile-bg);
  color: var(--mf-tile-fg);
}
.mf-stat-tile__label {
  grid-area: label;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-medium);
  line-height: var(--mf-leading-snug);
}
.mf-stat-tile__value {
  grid-area: value;
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
.mf-stat-tile__value strong {
  font-size: var(--mf-text-2xl);
  font-weight: var(--mf-weight-semibold);
  font-variant-numeric: tabular-nums lining-nums;
  line-height: 1.1;
}
.mf-stat-tile--zero .mf-stat-tile__value strong {
  color: var(--mf-color-text-tertiary);
}
.mf-stat-tile__unit {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.mf-stat-tile__hint {
  grid-area: hint;
  font-size: var(--mf-text-sm);
  line-height: var(--mf-leading-snug);
}
.mf-stat-tile__hint--neutral {
  color: var(--mf-color-text-secondary);
}
.mf-stat-tile__hint--info {
  color: var(--mf-tone-info-fg);
}
.mf-stat-tile__hint--success {
  color: var(--mf-tone-success-fg);
}
.mf-stat-tile__hint--warning {
  color: var(--mf-tone-warning-fg);
}
.mf-stat-tile__hint--danger {
  color: var(--mf-tone-danger-fg);
}
.mf-stat-tile__hint--pending {
  color: var(--mf-tone-pending-fg);
}
.mf-stat-tile__skeleton {
  grid-area: value;
  width: 64px;
  height: 30px;
  border-radius: var(--mf-radius-xs);
  background: var(--mf-color-surface-2);
}
.mf-stat-tile--unavailable {
  border: 1px dashed var(--mf-color-border-hover);
  background: var(--mf-color-surface-2);
  box-shadow: none;
}
.mf-stat-tile--unavailable .mf-stat-tile__icon {
  background: var(--mf-color-surface);
  color: var(--mf-color-text-secondary);
}
.mf-stat-tile__unavailable {
  grid-area: value;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-md);
  line-height: var(--mf-leading-snug);
}
</style>
