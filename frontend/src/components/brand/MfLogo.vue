<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * «Кадр и мазок» (design plan 8.1): a photo frame with the sun and one sea wave that enters the frame
 * on the left and leaves through a break in its right edge. Construction on a 24-unit grid.
 */
const props = withDefaults(
  defineProps<{
    variant?: 'horizontal' | 'compact' | 'stacked';
    /** Height of the mark in px; the wordmark scales with it. */
    size?: number;
    mono?: boolean;
  }>(),
  { variant: 'horizontal', size: 28, mono: false }
);

const clipId = useId();
const stroke = computed(() => (props.size <= 32 ? 2 : props.size >= 48 ? 1.5 : 1.75));
// The wave crosses the right edge at y ≈ 13.4; the edge is broken by the stroke plus 0.5u of air on each side.
const gapTop = computed(() => 13.4 - stroke.value / 2 - 0.5);
const gapBottom = computed(() => 13.4 + stroke.value / 2 + 0.5);
const frame = computed(
  () =>
    `M22 ${gapTop.value} V7 A2.5 2.5 0 0 0 19.5 4.5 H4.5 A2.5 2.5 0 0 0 2 7 V18 ` +
    `A2.5 2.5 0 0 0 4.5 20.5 H19.5 A2.5 2.5 0 0 0 22 18 V${gapBottom.value}`
);
</script>

<template>
  <span
    class="mf-logo"
    :class="[`mf-logo--${variant}`, { 'mf-logo--mono': mono }]"
    :style="{ '--mf-logo-size': size + 'px' }"
    role="img"
    aria-label="Море фото"
  >
    <svg class="mf-logo__mark" viewBox="0 0 24 24" :width="size" :height="size" aria-hidden="true" focusable="false">
      <defs v-if="'compact' === variant">
        <clipPath :id="clipId"><rect x="2" y="0" width="22" height="24" /></clipPath>
      </defs>
      <path :d="frame" fill="none" stroke="currentColor" :stroke-width="stroke" stroke-linejoin="round" stroke-linecap="butt" />
      <circle v-if="size > 20" cx="16.5" cy="9" r="1.6" fill="currentColor" />
      <path
        class="mf-logo__wave"
        d="M0.5 15.2 C5 10.4 8.5 18.6 13 14 S20.5 10.6 23.5 15.4"
        fill="none"
        :stroke-width="stroke"
        stroke-linecap="round"
        :clip-path="'compact' === variant ? `url(#${clipId})` : undefined"
      />
    </svg>
    <span v-if="'compact' !== variant" class="mf-logo__word" aria-hidden="true">Море <span>фото</span></span>
  </span>
</template>

<style scoped>
.mf-logo {
  display: inline-flex;
  align-items: center;
  gap: calc(var(--mf-logo-size) * 0.3);
  color: var(--mf-color-text);
  line-height: 1;
  white-space: nowrap;
}
.mf-logo__mark {
  flex: 0 0 auto;
  overflow: visible;
}
.mf-logo__wave {
  stroke: var(--mf-color-primary);
}
.mf-logo__word {
  font-family: var(--mf-font-display);
  font-size: calc(var(--mf-logo-size) * 0.75);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-tight);
}
.mf-logo__word span {
  color: var(--mf-color-primary);
  font-weight: var(--mf-weight-medium);
}
.mf-logo--stacked {
  flex-direction: column;
  gap: calc(var(--mf-logo-size) * 0.25);
}
.mf-logo--stacked .mf-logo__word {
  font-size: calc(var(--mf-logo-size) * 0.667);
}
.mf-logo--mono .mf-logo__wave {
  stroke: currentColor;
}
.mf-logo--mono .mf-logo__word span {
  color: inherit;
}
</style>
