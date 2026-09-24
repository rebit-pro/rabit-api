<script setup lang="ts">
const roles = [
  { role: 'display', spec: '40/44 · Manrope 700', sample: 'Фотографии группы «Солнышко»' },
  { role: 'h1', spec: '32/40 · Manrope 600', sample: 'Учреждения' },
  { role: 'h2', spec: '21/28 · Manrope 600', sample: 'Ссылки и сроки' },
  { role: 'h3', spec: '18/26 · Golos 600', sample: 'Новая съёмка' },
  { role: 'body', spec: '16/24 · Golos 400', sample: 'Сотрудник получит письмо со ссылкой для установки пароля; ссылка действует 7 дней.' },
  { role: 'body-sm', spec: '14/20 · Golos 400, tnum', sample: 'Детский сад «Радуга» · 12 групп · 1 480 ₽' },
  { role: 'caption', spec: '13/18 · Golos 400', sample: 'Создан 22.09.2026, 14:05 МСК' },
  { role: 'eyebrow', spec: '12/16 · Golos 600, 0.08em', sample: 'Организатор' },
  { role: 'kpi', spec: '28/32 · Golos 600, tnum', sample: '1 240' }
];
</script>

<template>
  <section id="ui-typography" class="mf-panel ui-example-section" aria-labelledby="ui-typography-title">
    <div>
      <h2 id="ui-typography-title">Типографика</h2>
      <p class="mf-muted">Golos Text для интерфейса и цифр, Manrope для заголовков и логотипа. Шрифты хранятся в проекте.</p>
    </div>
    <dl class="ui-type-scale">
      <template v-for="item in roles" :key="item.role">
        <dt class="ui-type-scale__role">
          <span>{{ item.role }}</span>
          <span class="ui-type-scale__spec">{{ item.spec }}</span>
        </dt>
        <dd :class="['ui-type-scale__sample', 'ui-type--' + item.role]">{{ item.sample }}</dd>
      </template>
    </dl>
  </section>
</template>

<style scoped>
.ui-type-scale {
  display: grid;
  grid-template-columns: minmax(0, 200px) minmax(0, 1fr);
  gap: var(--mf-space-4) var(--mf-space-6);
  align-items: baseline;
}
.ui-type-scale__role {
  display: grid;
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-medium);
}
.ui-type-scale__spec {
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-regular);
  color: var(--mf-color-text-secondary);
}
.ui-type-scale__sample {
  margin: 0;
  overflow-wrap: anywhere;
}
.ui-type--display {
  font: var(--mf-weight-bold) var(--mf-text-4xl) / 1.1 var(--mf-font-display);
  letter-spacing: var(--mf-tracking-display);
}
.ui-type--h1 {
  font: var(--mf-weight-semibold) var(--mf-text-3xl) / 1.25 var(--mf-font-display);
  letter-spacing: var(--mf-tracking-tight);
}
.ui-type--h2 {
  font: var(--mf-weight-semibold) var(--mf-text-xl) / 1.35 var(--mf-font-display);
}
.ui-type--h3 {
  font: var(--mf-weight-semibold) var(--mf-text-lg) / 1.45 var(--mf-font-sans);
}
.ui-type--body {
  font: var(--mf-weight-regular) var(--mf-text-base) / var(--mf-leading-normal) var(--mf-font-sans);
  max-width: 720px;
}
.ui-type--body-sm {
  font: var(--mf-weight-regular) var(--mf-text-md) / 1.43 var(--mf-font-sans);
  font-variant-numeric: tabular-nums lining-nums;
}
.ui-type--caption {
  font: var(--mf-weight-regular) var(--mf-text-sm) / 1.38 var(--mf-font-sans);
  color: var(--mf-color-text-tertiary);
}
.ui-type--eyebrow {
  font: var(--mf-weight-semibold) var(--mf-text-xs) / 1.33 var(--mf-font-sans);
  letter-spacing: var(--mf-tracking-caps);
  text-transform: uppercase;
  color: var(--mf-color-primary);
}
.ui-type--kpi {
  font: var(--mf-weight-semibold) var(--mf-text-2xl) / 1.14 var(--mf-font-sans);
  font-variant-numeric: tabular-nums lining-nums;
}
@media (max-width: 767px) {
  .ui-type-scale {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--mf-space-1);
  }
  .ui-type-scale__sample {
    margin-bottom: var(--mf-space-3);
  }
  .ui-type--display {
    font-size: 1.875rem;
  }
  .ui-type--h1 {
    font-size: 1.625rem;
  }
}
</style>
