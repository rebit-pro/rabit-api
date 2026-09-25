import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { render, TOKENS_SCSS } from '../../scripts/tokens-build.mjs';
import { contrastRatio } from '../../src/theme/contrast.ts';
import { MF_PASTELS, MF_SEMANTIC, MF_TONES, primitiveHex } from '../../src/theme/tokens.ts';

const color = (name) => primitiveHex(MF_SEMANTIC[name]);

function assertContrast(foreground, background, minimum, label) {
  const ratio = contrastRatio(foreground, background);
  assert.ok(ratio >= minimum, `${label}: ${ratio.toFixed(2)} < ${minimum}`);
}

test('generated _tokens.scss matches tokens.ts', () => {
  assert.equal(readFileSync(TOKENS_SCSS, 'utf8'), render(), 'Run npm run tokens:build');
});

test('semantic colors reference existing primitives', () => {
  for (const [name, primitive] of Object.entries(MF_SEMANTIC)) {
    assert.match(primitiveHex(primitive), /^#[0-9A-F]{6}$/, name);
  }
});

test('text and actions meet the contrast table of the design plan (7.6)', () => {
  for (const background of ['surface', 'bg', 'surface-2']) assertContrast(color('text'), color(background), 7, 'text/' + background);
  for (const background of ['surface', 'bg', 'surface-2', 'selected']) {
    assertContrast(color('text-secondary'), color(background), 4.5, 'text-secondary/' + background);
  }
  for (const background of ['surface', 'bg']) assertContrast(color('text-tertiary'), color(background), 4.5, 'text-tertiary/' + background);
  for (const action of ['primary', 'primary-hover', 'primary-active'])
    assertContrast(color('on-primary'), color(action), 4.5, 'on-primary/' + action);
  for (const background of ['surface', 'bg', 'primary-soft']) assertContrast(color('link'), color(background), 4.5, 'link/' + background);
  assertContrast(color('nav-active-fg'), color('nav-active-bg'), 4.5, 'nav-active');
  assertContrast(color('nav-fg'), color('nav-bg'), 4.5, 'nav');
});

test('non-text UI keeps 3:1 (WCAG 1.4.11)', () => {
  for (const background of ['surface', 'bg']) {
    assertContrast(color('focus'), color(background), 3, 'focus/' + background);
    assertContrast(color('border-strong'), color(background), 3, 'border-strong/' + background);
  }
});

test('status tones and avatar pastels are readable', () => {
  for (const [tone, { fg, bg }] of Object.entries(MF_TONES)) assertContrast(fg, bg, 4.5, 'tone ' + tone);
  for (const pastel of MF_PASTELS) assertContrast(pastel.fg, pastel.bg, 4.5, 'pastel ' + pastel.name);
});
