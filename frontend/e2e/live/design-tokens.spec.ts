import { test, expect } from '@playwright/test';

// Design system U2: size tokens of UI01–UI04 are unchanged, fields and buttons follow the new radius and outline,
// fonts are self-hosted under /assets/ (hashed, immutable cache).
test('токены размеров, радиус и рамка поля, шрифты из собственной сборки', async ({ page }) => {
  const fonts: string[] = [];
  page.on('response', (response) => {
    if (response.url().endsWith('.woff2')) fonts.push(new URL(response.url()).pathname);
  });
  await page.goto('/login');
  const email = page.getByRole('textbox', { name: 'Email', exact: true });
  await expect(email).toBeVisible();
  await page.evaluate(() => document.fonts.ready);

  const tokens = await page
    .locator('.morefoto-app')
    .first()
    .evaluate((app) => {
      const style = getComputedStyle(app);
      return ['--mf-control-height', '--mf-control-compact', '--mf-touch-size', '--mf-focus-width', '--mf-radius-sm'].map((name) =>
        style.getPropertyValue(name).trim()
      );
    });
  expect(tokens).toEqual(['48px', '40px', '44px', '2px', '8px']);

  const outline = await page
    .locator('.v-input', { has: email })
    .locator('.v-field__outline')
    .evaluate((el) => {
      const start = getComputedStyle(el.querySelector('.v-field__outline__start')!);
      return {
        opacity: getComputedStyle(el).getPropertyValue('--v-field-border-opacity').trim(),
        radius: start.borderTopLeftRadius,
        color: start.borderTopColor
      };
    });
  expect(outline).toEqual({ opacity: '1', radius: '8px', color: 'rgb(115, 133, 150)' });

  const button = page.getByRole('button', { name: 'Войти', exact: true });
  expect(await button.evaluate((el) => [getComputedStyle(el).borderTopLeftRadius, getComputedStyle(el).textTransform])).toEqual([
    '8px',
    'none'
  ]);

  expect(await email.evaluate((el) => getComputedStyle(el).fontFamily)).toContain('Golos Text');
  expect(await page.evaluate(() => document.fonts.check('16px "Golos Text"'))).toBe(true);
  expect(fonts.some((path) => /^\/assets\/GolosText-wght-[\w-]+\.woff2$/.test(path))).toBe(true);
});
