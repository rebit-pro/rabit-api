<script setup lang="ts">
import type { StatusTone } from './tones';

withDefaults(defineProps<{ tone?: StatusTone; icon?: string | null }>(), { tone: 'neutral', icon: null });
</script>

<template>
  <span class="mf-status" :class="`mf-status--${tone}`">
    <v-icon v-if="icon" :icon="icon" size="14" class="mf-status__icon" aria-hidden="true" />
    <span v-else class="mf-status__dot" aria-hidden="true"></span>
    <span class="mf-status__text"><slot /></span>
  </span>
</template>

<style scoped>
.mf-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  padding: 3px var(--mf-space-2);
  border: 1px solid var(--mf-status-border);
  border-radius: var(--mf-radius-xs);
  background: var(--mf-status-bg);
  color: var(--mf-status-fg);
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-medium);
  line-height: var(--mf-leading-snug);
  vertical-align: middle;
}
.mf-status__dot {
  flex: 0 0 auto;
  width: 6px;
  height: 6px;
  border-radius: var(--mf-radius-full);
  background: currentColor;
}
.mf-status__icon {
  flex: 0 0 auto;
}
.mf-status__text {
  min-width: 0;
  overflow-wrap: anywhere;
}
.mf-status--success {
  --mf-status-fg: var(--mf-tone-success-fg);
  --mf-status-bg: var(--mf-tone-success-bg);
  --mf-status-border: var(--mf-tone-success-border);
}
.mf-status--warning {
  --mf-status-fg: var(--mf-tone-warning-fg);
  --mf-status-bg: var(--mf-tone-warning-bg);
  --mf-status-border: var(--mf-tone-warning-border);
}
.mf-status--danger {
  --mf-status-fg: var(--mf-tone-danger-fg);
  --mf-status-bg: var(--mf-tone-danger-bg);
  --mf-status-border: var(--mf-tone-danger-border);
}
.mf-status--info {
  --mf-status-fg: var(--mf-tone-info-fg);
  --mf-status-bg: var(--mf-tone-info-bg);
  --mf-status-border: var(--mf-tone-info-border);
}
.mf-status--neutral {
  --mf-status-fg: var(--mf-tone-neutral-fg);
  --mf-status-bg: var(--mf-tone-neutral-bg);
  --mf-status-border: var(--mf-tone-neutral-border);
}
.mf-status--pending {
  --mf-status-fg: var(--mf-tone-pending-fg);
  --mf-status-bg: var(--mf-tone-pending-bg);
  --mf-status-border: var(--mf-tone-pending-border);
}
</style>
