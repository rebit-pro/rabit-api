<script setup lang="ts">
import { contrastRatio } from '@/theme/contrast';
import { MF_PASTELS, MF_RADIUS, MF_SEMANTIC, MF_SHADOW, MF_TONES, MF_WHITE, primitiveHex } from '@/theme/tokens';

const surfaceGroups = ['bg', 'surface', 'surface-2', 'surface-inverse', 'selected', 'border', 'border-strong'];
const textGroups = ['text', 'text-secondary', 'text-tertiary', 'link', 'primary', 'primary-hover', 'focus', 'accent'];
const swatch = (name: string) => {
  const hex = primitiveHex(MF_SEMANTIC[name]!);
  return { name, hex, contrast: contrastRatio(hex, MF_WHITE).toFixed(2) };
};
const surfaces = surfaceGroups.map(swatch);
const texts = textGroups.map(swatch);
const tones = Object.entries(MF_TONES).map(([name, tone]) => ({ name, ...tone, contrast: contrastRatio(tone.fg, tone.bg).toFixed(2) }));
const pastels = MF_PASTELS.map((pastel) => ({ ...pastel, contrast: contrastRatio(pastel.fg, pastel.bg).toFixed(2) }));
const radii = Object.entries(MF_RADIUS).map(([name, px]) => ({ name, px }));
const shadows = Object.keys(MF_SHADOW).filter((name) => 'ring-surface' !== name);
</script>

<template>
  <section id="ui-tokens" class="mf-panel ui-example-section" aria-labelledby="ui-tokens-title">
    <div>
      <h2 id="ui-tokens-title">Токены</h2>
      <p class="mf-muted">Цвета, радиусы и тени из src/theme/tokens.ts. Контраст указан к белому фону или к фону тона.</p>
    </div>
    <h3 class="ui-token-heading">Поверхности и границы</h3>
    <ul class="ui-swatches">
      <li v-for="item in surfaces" :key="item.name" class="ui-swatch">
        <span class="ui-swatch__chip" :style="{ background: `var(--mf-color-${item.name})` }" aria-hidden="true"></span>
        <span class="ui-swatch__name">{{ item.name }}</span>
        <span class="ui-swatch__meta">{{ item.hex }}</span>
      </li>
    </ul>
    <h3 class="ui-token-heading">Текст и действия</h3>
    <ul class="ui-swatches">
      <li v-for="item in texts" :key="item.name" class="ui-swatch">
        <span class="ui-swatch__chip" :style="{ background: `var(--mf-color-${item.name})` }" aria-hidden="true"></span>
        <span class="ui-swatch__name">{{ item.name }}</span>
        <span class="ui-swatch__meta">{{ item.hex }} · {{ item.contrast }}:1</span>
      </li>
    </ul>
    <h3 class="ui-token-heading">Статусы</h3>
    <ul class="ui-tones">
      <li
        v-for="tone in tones"
        :key="tone.name"
        class="ui-tone"
        :style="{
          color: `var(--mf-tone-${tone.name}-fg)`,
          background: `var(--mf-tone-${tone.name}-bg)`,
          borderColor: `var(--mf-tone-${tone.name}-border)`
        }"
      >
        {{ tone.name }} · {{ tone.contrast }}:1
      </li>
    </ul>
    <h3 class="ui-token-heading">Пастель данных</h3>
    <ul class="ui-pastels">
      <li v-for="(pastel, index) in pastels" :key="pastel.name" class="ui-pastel">
        <span class="ui-pastel__avatar" :style="{ color: `var(--mf-avatar-${index}-fg)`, background: `var(--mf-avatar-${index}-bg)` }">
          {{ pastel.name.slice(0, 2).toUpperCase() }}
        </span>
        <span
          class="ui-pastel__bar"
          :style="{ background: `var(--mf-chart-${index})`, borderColor: `var(--mf-chart-${index}-strong)` }"
        ></span>
        <span class="ui-swatch__meta">{{ pastel.name }} · {{ pastel.contrast }}:1</span>
      </li>
    </ul>
    <div class="ui-shape-grid">
      <div>
        <h3 class="ui-token-heading">Радиусы</h3>
        <ul class="ui-radii">
          <li v-for="radius in radii" :key="radius.name" class="ui-radius" :style="{ borderRadius: `var(--mf-radius-${radius.name})` }">
            {{ radius.name }} · {{ 9999 === radius.px ? 'full' : radius.px + 'px' }}
          </li>
        </ul>
      </div>
      <div>
        <h3 class="ui-token-heading">Тени</h3>
        <ul class="ui-shadows">
          <li v-for="shadow in shadows" :key="shadow" class="ui-shadow" :style="{ boxShadow: `var(--mf-shadow-${shadow})` }">
            {{ shadow }}
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ui-token-heading {
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
  margin-top: var(--mf-space-2);
}
.ui-swatches,
.ui-tones,
.ui-pastels,
.ui-radii,
.ui-shadows {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr));
  gap: var(--mf-space-3);
  list-style: none;
  padding: 0;
}
.ui-swatch {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  column-gap: var(--mf-space-3);
  align-items: center;
}
.ui-swatch__chip {
  grid-row: span 2;
  width: 40px;
  height: 40px;
  border-radius: var(--mf-radius-sm);
  border: 1px solid var(--mf-color-border);
}
.ui-swatch__name {
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-medium);
}
.ui-swatch__meta {
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
  font-variant-numeric: tabular-nums;
}
.ui-tone {
  padding: var(--mf-space-1) var(--mf-space-3);
  border: 1px solid;
  border-radius: var(--mf-radius-xs);
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-medium);
}
.ui-pastel {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  column-gap: var(--mf-space-3);
  row-gap: var(--mf-space-1);
  align-items: center;
}
.ui-pastel__avatar {
  grid-row: span 2;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--mf-radius-full);
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-semibold);
}
.ui-pastel__bar {
  height: 12px;
  border: 1px solid;
  border-radius: var(--mf-radius-xs);
}
.ui-shape-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
  gap: var(--mf-space-6);
}
.ui-radius,
.ui-shadow {
  display: grid;
  place-items: center;
  min-height: 72px;
  background: var(--mf-color-surface);
  border: 1px solid var(--mf-color-border);
  font-size: var(--mf-text-sm);
}
.ui-radius {
  background: var(--mf-color-selected);
}
.ui-shadow {
  border-radius: var(--mf-radius-md);
}
</style>
