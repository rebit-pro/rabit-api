import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { MF_ICONS } from '../../src/plugins/icons.ts';

function sources(dir) {
  return readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) return sources(path);
    return /\.(vue|ts)$/.test(entry) && !path.endsWith('plugins/icons.ts') ? [path] : [];
  });
}

test('every mdi-* name used in src has an SVG path in the registry', () => {
  const used = new Set();
  for (const file of sources(new URL('../../src', import.meta.url).pathname)) {
    for (const match of readFileSync(file, 'utf8').matchAll(/['"`]mdi-([a-z0-9]+(?:-[a-z0-9]+)*)['"`]/g)) used.add('mdi-' + match[1]);
  }
  assert.ok(used.size > 40);
  assert.deepEqual(
    [...used].filter((name) => !(name in MF_ICONS)),
    []
  );
});

test('registry entries are SVG paths', () => {
  for (const [name, path] of Object.entries(MF_ICONS)) assert.match(path, /^M[\d.\s,MLHVCSQTAZmlhvcsqtaz-]+$/, name);
});
