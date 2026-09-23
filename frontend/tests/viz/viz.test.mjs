import { test } from 'node:test';
import assert from 'node:assert/strict';
import { countdown, countLabel, percent, plural } from '../../src/components/viz/measures.ts';
import { CHART_CATEGORY } from '../../src/modules/morefoto/ui/chartPalette.ts';

const now = '2026-09-23T09:00:00+03:00';
const at = (hours) => new Date(Date.parse(now) + hours * 3600 * 1000).toISOString();

test('countdown tones follow the thresholds of plan 9.3.5', () => {
  assert.deepEqual(countdown(null, null, now), { days: null, tone: 'neutral', label: '—' });
  assert.equal(countdown(at(-24 * 30), at(24 * 9), now).tone, 'neutral');
  assert.equal(countdown(at(-24 * 30), at(24 * 7), now).tone, 'info');
  assert.equal(countdown(at(-24 * 30), at(24 * 3), now).tone, 'info');
  assert.equal(countdown(at(-24 * 30), at(24 * 2), now).tone, 'warning');
  assert.deepEqual(countdown(at(-24 * 30), at(5), now), { days: 1, tone: 'warning', label: 'остался 1 день' });
  assert.deepEqual(countdown(at(-24 * 30), at(0), now), { days: 0, tone: 'danger', label: 'приём закрыт' });
  assert.deepEqual(countdown(at(-24 * 30), at(-49), now), { days: -2, tone: 'danger', label: 'просрочено на 2 дня' });
  assert.equal(countdown(at(-24 * 30), at(24 * 5), now).label, 'осталось 5 дней');
});

test('numbers always come with their word', () => {
  assert.equal(plural(1, ['группа', 'группы', 'групп']), 'группа');
  assert.equal(plural(3, ['группа', 'группы', 'групп']), 'группы');
  assert.equal(plural(11, ['группа', 'группы', 'групп']), 'групп');
  assert.equal(plural(22, ['группа', 'группы', 'групп']), 'группы');
  assert.equal(countLabel(1250, ['заказ', 'заказа', 'заказов']), '1 250 заказов'.replace(' ', ' '));
  assert.equal(percent(1, 3), 33);
  assert.equal(percent(0, 0), 0);
});

test('every category has its own pastel of the eight', () => {
  const indices = Object.values(CHART_CATEGORY);
  assert.equal(new Set(indices).size, indices.length);
  assert.ok(indices.every((index) => index >= 0 && index <= 7));
  assert.equal(CHART_CATEGORY.other, 7);
});
