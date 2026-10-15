import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { buyerGuideHref, buyerGuidePath } from '../../src/modules/morefoto/guide/buyerGuide.ts';

// #192: the parents' guide page the organizer shares in a group chat, and its link on every purchase step.
const root = new URL('../../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const page = read('public/kak-kupit/index.html');
const meta = (property) => page.match(new RegExp(`<meta (?:property|name)="${property}" content="([^"]*)"`))?.[1];

test('KK-T03: the guide page carries a messenger preview with an absolute image that exists', () => {
  assert.match(page, /<html lang="ru">/);
  assert.match(page, /<meta name="viewport" content="width=device-width, initial-scale=1/);
  assert.equal(meta('og:title'), 'Как купить фото — 6 шагов с телефона');
  assert.ok(meta('og:description'));
  assert.equal(meta('og:url'), 'https://app.morefoto36.ru' + buyerGuidePath);
  assert.equal(meta('og:image'), 'https://app.morefoto36.ru/kak-kupit/og.jpg');
  assert.equal(meta('twitter:card'), 'summary_large_image');
  assert.ok(existsSync(new URL('public/kak-kupit/og.jpg', root)));
});

test('KK-T01: six steps, each with an existing screenshot that opens in the shared viewer', () => {
  const cards = [...page.matchAll(/<li class="kk-card" id="([a-z]+)">[\s\S]*?<\/li>/g)];
  assert.deepEqual(
    cards.map((card) => card[1]),
    ['rebenok', 'foto', 'zakaz', 'oplata', 'karta', 'gotovo']
  );
  for (const [card] of cards) {
    const [, href, src, alt] = card.match(/<figure class="shot"><a href="([^"]+)"><img src="([^"]+)"[^>]* alt="([^"]+)"/) ?? [];
    assert.equal(href, src);
    assert.ok(existsSync(new URL('public' + src, root)), src);
    assert.match(alt, /^Шаг \d\. /);
  }
  for (const asset of [...page.matchAll(/(?:href|src)="(\/(?:guide|kak-kupit|icons)\/[^"?#]+)/g)].map((match) => match[1]))
    assert.ok(existsSync(new URL('public' + asset, root)), asset);
});

test('KK-T04: every purchase step links to the guide, step anchors exist on the page', () => {
  assert.equal(buyerGuideHref(), '/kak-kupit/');
  assert.equal(buyerGuideHref('oplata'), '/kak-kupit/#oplata');
  const screens = [
    'gallery/components/GalleryHeader.vue',
    'gallery/components/GalleryHelp.vue',
    'commerce/components/ProductSelector.vue',
    'commerce/components/CartScreen.vue',
    'orders/components/OrderLiveScreen.vue',
    'orders/components/PaymentReturnScreen.vue'
  ];
  for (const screen of screens) {
    const source = read('src/modules/morefoto/' + screen);
    assert.match(source, /<BuyerGuideLink\b/, screen);
    for (const [, step] of source.matchAll(/step="([a-z]+)"|'(?:gotovo|oplata)'/g))
      if (step) assert.match(page, new RegExp(`id="${step}"`), screen);
  }
  const link = read('src/modules/morefoto/guide/BuyerGuideLink.vue');
  assert.match(link, /target="_blank" rel="noopener"/);
});

test('KK-T05: the buyer footer leads to the guide', () => {
  const footer = read('src/modules/morefoto/legal/components/LegalFooter.vue');
  assert.match(footer, /<a :href="buyerGuidePath"[^>]*>Как купить фото<\/a>/);
});

for (const env of ['production', 'development']) {
  test(`KK-T06: ${env} frontend nginx keeps directory redirects relative behind the TLS proxy`, () => {
    assert.match(read(`docker/${env}/nginx/conf.d/default.conf`), /^\s*absolute_redirect off;/m);
  });
}
