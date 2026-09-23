import { test, expect, type Request } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { login, token } from './helpers.js';

// Opt-in measurement for #33/#34: E2E_MEDIA_BENCH=1 (optionally E2E_MEDIA_BENCH_COUNT) on the isolated stand only.
const count = Number(process.env.E2E_MEDIA_BENCH_COUNT ?? 50);

function percentile(values: number[], share: number): number {
  const sorted = [...values].sort((left, right) => left - right);
  return sorted[Math.min(sorted.length - 1, Math.max(0, Math.ceil(share * sorted.length) - 1))] ?? 0;
}

test('#33/#34: замер партии типичных фото на изолированном стенде', async ({ page }, testInfo) => {
  test.setTimeout(45 * 60 * 1000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const auth = async () => ({ Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') });
  const create = async (path: string, data: Record<string, string>) => {
    const response = await page.request.post(path, { headers: await auth(), data });
    expect(response.status(), await response.text()).toBe(201);
    return (await response.json()).data as { id: string };
  };
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create('/api/v1/institutions', { name: 'Бенч ' + suffix, address: 'Москва' });
  const shoot = await create('/api/v1/institutions/' + institution.id + '/shoots', { name: 'Бенч съёмка', date: '2026-10-20' });
  const group = await create('/api/v1/shoots/' + shoot.id + '/groups', { name: 'Бенч группа', groupKind: 'regular' });
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id);
  await expect(page.getByText('Бенч группа', { exact: true })).toBeVisible();

  // 24 MP JPEGs with blended noise land close to the 7–8 MB of typical camera originals.
  const directory = testInfo.outputPath('bench-files');
  mkdirSync(directory, { recursive: true });
  const paths: string[] = [];
  let bytes = 0;
  for (let index = 0; index < count; index++) {
    const encoded = await page.evaluate(async (seed) => {
      const canvas = new OffscreenCanvas(6000, 4000);
      const context = canvas.getContext('2d')!;
      const gradient = context.createLinearGradient(0, 0, 6000, 4000);
      gradient.addColorStop(0, 'hsl(' + ((seed * 47) % 360) + ' 55% 40%)');
      gradient.addColorStop(1, 'hsl(' + ((seed * 47 + 150) % 360) + ' 45% 65%)');
      context.fillStyle = gradient;
      context.fillRect(0, 0, 6000, 4000);
      const tile = new OffscreenCanvas(1000, 1000);
      const tileContext = tile.getContext('2d')!;
      const noise = tileContext.createImageData(1000, 1000);
      let state = (seed * 2654435761) >>> 0;
      for (let offset = 0; offset < noise.data.length; offset += 4) {
        state = (state * 1664525 + 1013904223) >>> 0;
        const value = state >>> 24;
        noise.data[offset] = value;
        noise.data[offset + 1] = (value * 3) & 255;
        noise.data[offset + 2] = (value * 5) & 255;
        noise.data[offset + 3] = 36;
      }
      tileContext.putImageData(noise, 0, 0);
      for (let y = 0; y < 4000; y += 1000) for (let x = 0; x < 6000; x += 1000) context.drawImage(tile, x, y);
      const buffer = new Uint8Array(await (await canvas.convertToBlob({ type: 'image/jpeg', quality: 0.9 })).arrayBuffer());
      let binary = '';
      for (let offset = 0; offset < buffer.length; offset += 0x8000)
        binary += String.fromCharCode(...buffer.subarray(offset, offset + 0x8000));
      return btoa(binary);
    }, index + 1);
    const file = Buffer.from(encoded, 'base64');
    bytes += file.length;
    paths.push(directory + '/bench-' + String(index + 1).padStart(3, '0') + '.jpg');
    writeFileSync(paths[index]!, file);
  }

  const uploads = '/api/v1/shoots/' + shoot.id + '/photos';
  const isUpload = (request: Request) => request.method() === 'POST' && new URL(request.url()).pathname === uploads;
  // Transfer time and overlap come from the browser network timings, not from the event delivery order.
  const spans: { start: number; end: number }[] = [];
  const transfer: number[] = [];
  const accepted = new Map<string, number>();
  const ready = new Map<string, number>();
  page.on('requestfinished', (request) => {
    if (!isUpload(request)) return;
    const timing = request.timing();
    spans.push({ start: timing.startTime, end: timing.startTime + Math.max(timing.responseEnd, 0) });
    transfer.push(Math.round(Math.max(timing.responseEnd, 0)));
  });
  page.on('response', async (response) => {
    const request = response.request();
    const path = new URL(response.url()).pathname;
    if (isUpload(request)) {
      if (202 === response.status()) accepted.set((await response.json()).data.id, Date.now());
    } else if (request.method() === 'GET' && /^\/api\/v1\/photos\/[0-9a-f-]{36}$/.test(path) && response.ok()) {
      const photo = (await response.json()).data as { id: string; status: string };
      if ('ready' === photo.status && !ready.has(photo.id)) ready.set(photo.id, Date.now());
    }
  });
  const overlap = () => {
    const edges = spans.flatMap(({ start, end }) => [
      { at: start, delta: 1 },
      { at: end, delta: -1 }
    ]);
    edges.sort((left, right) => left.at - right.at || left.delta - right.delta);
    let current = 0;

    return edges.reduce((peak, edge) => Math.max(peak, (current += edge.delta)), 0);
  };

  const begin = Date.now();
  await page.locator('input[type="file"][aria-label="Выбрать фотографии"]').setInputFiles(paths);
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  await expect(page.getByTestId('upload-counts')).toContainText('Готово: ' + count, { timeout: 40 * 60 * 1000 });
  const total = Date.now() - begin;
  const readiness = [...accepted].filter(([id]) => ready.has(id)).map(([id, time]) => ready.get(id)! - time);
  const report = {
    count,
    averageMegabytes: Math.round((bytes / count / 1024 / 1024) * 10) / 10,
    parallel: overlap(),
    totalSeconds: Math.round(total / 1000),
    transferMs: { p50: percentile(transfer, 0.5), p95: percentile(transfer, 0.95) },
    acceptedToReadyMs: { p50: percentile(readiness, 0.5), p95: percentile(readiness, 0.95) }
  };
  writeFileSync(testInfo.outputPath('media-bench.json'), JSON.stringify(report, null, 2));
  console.log('Media bench: ' + JSON.stringify(report));
  expect(overlap()).toBeLessThanOrEqual(2);
  expect(readiness).toHaveLength(count);
});
