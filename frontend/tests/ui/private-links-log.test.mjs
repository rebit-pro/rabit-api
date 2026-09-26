import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Review #143 (1): FIL-04 links carry a signed token; the frontend proxy must not write them to its access log.
const skipped = (config) => {
  const map = config.match(/map \$request_uri \$gallery_access_log \{([\s\S]*?)\}/)?.[1] ?? '';
  const rules = [...map.matchAll(/^\s*~(\S+)\s+0;/gm)].map((match) => new RegExp(match[1]));
  return (uri) => rules.some((rule) => rule.test(uri));
};

for (const env of ['production', 'development']) {
  test(`${env} frontend nginx keeps download links, galleries and order keys out of the access log`, () => {
    const config = readFileSync(new URL(`../../docker/${env}/nginx/conf.d/default.conf`, import.meta.url), 'utf8');
    const isSkipped = skipped(config);
    assert.match(config, /access_log \S+ combined if=\$gallery_access_log;/);
    assert.equal(isSkipped('/api/v1/public/orders/current/downloads/d1/content?token=1790000000.ab'), true);
    assert.equal(isSkipped('/api/v1/public/galleries/t/photos'), true);
    assert.equal(isSkipped('/orders/access/k'), true);
    assert.equal(isSkipped('/api/v1/public/orders/current'), false);
  });
}
