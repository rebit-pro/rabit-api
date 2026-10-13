import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';

// F3: the short link staff copy opens the same gallery as the long key; the address becomes the long link (F3-DEC-04).
type Gallery = { token: string; alias: string; groupId: string };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Record<'open' | 'digital' | 'revoked', Gallery>;
const api = (key: string) => '/api/v1/public/galleries/' + key;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

test('F3-T06 F3-T07: the alias resolves like the long key and nothing else', async ({ request }) => {
  for (const kind of ['open', 'digital'] as const) {
    expect(fixture[kind].alias).toMatch(/^[2-9a-km-np-z]{10}$/);
    const [short, long] = [
      (await body(await request.get(api(fixture[kind].alias)))).data,
      (await body(await request.get(api(fixture[kind].token)))).data
    ];
    // E7 windows hold for the alias as for the key; the answer names the long key the screen moves to.
    expect([short.state, short.groupId, short.token]).toEqual([long.state, fixture[kind].groupId, fixture[kind].token]);
    expect(short.children[0].photos[0].thumbSrc).toContain('/galleries/' + fixture[kind].token + '/');
  }
  expect(fixture.digital.alias).not.toBe(fixture.open.alias);
  // A revoked key takes its alias with it; an unknown or malformed alias is just not found.
  for (const key of [fixture.revoked.alias, 'zzzzzzzzzz', fixture.open.alias.toUpperCase(), fixture.open.alias + 'a', 'o0o0o0o0o0']) {
    expect((await body(await request.get(api(key)), 404)).error.code).toBe('GALLERY_NOT_FOUND');
  }
  // Every other public call keeps taking only the long key.
  expect((await body(await request.get(api(fixture.open.alias) + '/catalog'), 404)).error.code).toBe('GALLERY_NOT_FOUND');
});

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test('F3-T06: a parent opens the short link on ' + viewport.name, async ({ page }, info) => {
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/g/' + fixture.open.alias + '?child=A');
    await expect(page).toHaveURL(new RegExp('/g/' + fixture.open.token + '\\?child=A$'));
    await expect(page.getByRole('button', { name: 'Открыть кадр A001', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath('f3-' + viewport.name + '-short-link.png'), fullPage: true, animations: 'disabled' });

    await page.goto('/g/zzzzzzzzzz');
    await expect(page.getByText('Ссылка недействительна')).toBeVisible();
    expect(errors).toEqual([]);
  });
}
