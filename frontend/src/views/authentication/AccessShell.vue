<script setup lang="ts">
import MfLogo from '@/components/brand/MfLogo.vue';

defineProps<{ title: string; description?: string }>();
// Optional header (logo + actions) and aside (information next to the form); pages without them keep the centred card.
const slots = defineSlots<{ default(): unknown; header?(): unknown; aside?(): unknown }>();
</script>

<template>
  <div class="access-page" :class="{ 'access-page--aside': slots.aside }">
    <header v-if="slots.header" class="access-header">
      <MfLogo :size="32" />
      <slot name="header" />
    </header>
    <main class="access-main">
      <div class="access-stack">
        <MfLogo v-if="!slots.header" variant="stacked" :size="48" />
        <v-card tag="section" aria-labelledby="access-title" elevation="0" class="access-card">
          <h1 id="access-title" class="access-title">{{ title }}</h1>
          <p v-if="description" class="access-description">{{ description }}</p>
          <slot />
        </v-card>
        <svg class="access-wave" viewBox="0 0 120 12" aria-hidden="true" focusable="false">
          <path d="M2 8 C 22 1, 38 13, 60 6 S 98 1, 118 8" fill="none" stroke-width="2" stroke-linecap="round" />
        </svg>
      </div>
      <aside v-if="slots.aside" class="access-aside">
        <slot name="aside" />
      </aside>
    </main>
  </div>
</template>

<style scoped lang="scss">
.access-page {
  display: grid;
  grid-template-rows: auto 1fr;
  min-height: 100vh;
  min-height: 100svh;
  background: linear-gradient(180deg, var(--mf-color-primary-soft), var(--mf-color-surface) 70%);
}

.access-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-4);
  width: 100%;
  max-width: 920px;
  margin: 0 auto;
  padding: var(--mf-space-4);
}

.access-main {
  display: grid;
  grid-row: 2;
  place-items: center;
  padding: var(--mf-space-6) var(--mf-space-4);
}

.access-page--aside .access-main {
  place-items: start center;
  gap: var(--mf-space-8);
}

.access-stack {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-6);
  width: 100%;
  max-width: 440px;
}

.access-card {
  width: 100%;
  padding: var(--mf-space-8) var(--mf-space-6) var(--mf-space-6);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-lg);
  box-shadow: var(--mf-shadow-sm);
}

.access-title {
  color: var(--mf-color-text);
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-2xl);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-tight);
  line-height: var(--mf-leading-tight);
  overflow-wrap: anywhere;
}

.access-description {
  margin-top: var(--mf-space-3);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-base);
  line-height: var(--mf-leading-normal);
}

.access-wave {
  width: 120px;
  height: 12px;
  stroke: var(--mf-color-accent);
}

.access-aside {
  width: 100%;
  max-width: 440px;
}

@media (min-width: 960px) {
  .access-page--aside .access-main {
    grid-template-columns: minmax(0, 440px) minmax(0, 400px);
    justify-content: center;
    align-items: center;
    gap: var(--mf-space-12);
    padding-top: var(--mf-space-8);
  }
}

@media (max-width: 479px) {
  .access-card {
    padding: var(--mf-space-6) var(--mf-space-4) var(--mf-space-4);
  }

  .access-title {
    font-size: 1.625rem;
  }
}
</style>
