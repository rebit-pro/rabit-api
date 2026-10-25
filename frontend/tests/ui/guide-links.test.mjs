import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

// The knowledge base is static HTML: a renamed screenshot or page is only caught here, not by the app build.
const PUBLIC = new URL('../../public/', import.meta.url).pathname;
const pages = (dir) =>
  readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) return pages(path);
    return entry === 'index.html' ? [path] : [];
  });
const file = (path) => join(PUBLIC, path.endsWith('/') ? path + 'index.html' : path);

test('GR-T01: every /guide/ link and image of the knowledge base exists, anchors included', () => {
  const all = pages(join(PUBLIC, 'guide'));
  assert.ok(all.some((page) => page.endsWith('guide/reports/index.html')));
  const missing = [];
  for (const page of all) {
    const html = readFileSync(page, 'utf8');
    for (const [, target] of html.matchAll(/(?:href|src)="(\/guide\/[^"?]*)"/g)) {
      const [path, anchor] = target.split('#');
      if (!existsSync(file(path))) {
        missing.push(page.replace(PUBLIC, '') + ' → ' + target);
        continue;
      }
      if (anchor && !readFileSync(file(path), 'utf8').includes('id="' + anchor + '"'))
        missing.push(page.replace(PUBLIC, '') + ' → #' + anchor);
    }
  }
  assert.deepEqual(missing, []);
});

test('GR-T01: the reports article is linked from the home page and every role that has the section', () => {
  for (const page of [
    'guide/index.html',
    'guide/team/index.html',
    'guide/curator/index.html',
    'guide/organizer/index.html',
    'guide/faq/index.html'
  ]) {
    assert.match(readFileSync(join(PUBLIC, page), 'utf8'), /href="\/guide\/reports\//, page);
  }
});
