import test from 'node:test';
import assert from 'node:assert/strict';
import { createPreviewLoader, isPreviewAbort } from '../../src/modules/morefoto/photos/preview-loader.ts';

function harness(options = {}) {
  const calls = [];
  const revoked = [];
  let active = 0;
  let peak = 0;
  let created = 0;
  const loader = createPreviewLoader({
    concurrency: 2,
    retryDelayMs: 0,
    maxBytes: 1000,
    wait: () => Promise.resolve(),
    retryable: (error) => error?.retryable === true,
    createUrl: () => 'blob:frame-' + ++created,
    revokeUrl: (url) => revoked.push(url),
    fetch: (url, signal) =>
      new Promise((resolve, reject) => {
        active++;
        peak = Math.max(peak, active);
        calls.push({
          url,
          signal,
          resolve(size = 10) {
            active--;
            resolve(new Blob([new Uint8Array(size)]));
          },
          reject(error) {
            active--;
            reject(error);
          }
        });
      }),
    ...options
  });
  return { loader, calls, revoked, peak: () => peak };
}
const settle = () => new Promise((resolve) => setImmediate(resolve));

test('Превью: одновременно выполняется не больше заданного числа запросов', async () => {
  const { loader, calls, peak } = harness();
  const frames = ['a', 'b', 'c', 'd', 'e'].map((name) => loader.load('/frames/' + name));
  for (let index = 0; index < frames.length; index++) {
    await settle();
    assert.ok(calls.length <= index + 2);
    calls[index].resolve();
  }
  const urls = await Promise.all(frames);
  assert.equal(new Set(urls).size, 5);
  assert.equal(peak(), 2);
});

test('Превью: временный сбой повторяется один раз, отказ сервера не повторяется', async () => {
  const { loader, calls } = harness();
  const recovered = loader.load('/frames/a');
  await settle();
  calls[0].reject({ retryable: true });
  await settle();
  assert.equal(calls.length, 2);
  calls[1].resolve();
  assert.match(await recovered, /^blob:/);

  const missing = loader.load('/frames/b');
  await settle();
  calls[2].reject({ status: 404 });
  await assert.rejects(missing, (error) => error.status === 404);

  const broken = loader.load('/frames/c');
  await settle();
  calls[3].reject({ retryable: true });
  await settle();
  calls[4].reject({ retryable: true, last: true });
  await assert.rejects(broken, (error) => error.last === true);
  assert.equal(calls.length, 5);
});

test('Превью: один кадр запрашивается один раз и дальше берётся из кеша', async () => {
  const { loader, calls } = harness();
  const first = loader.load('/frames/a');
  const second = loader.load('/frames/a');
  await settle();
  assert.equal(calls.length, 1);
  calls[0].resolve();
  const [url, same] = await Promise.all([first, second]);
  assert.equal(url, same);
  assert.equal(await loader.load('/frames/a'), url);
  assert.equal(calls.length, 1);
});

test('Превью: ушедший из очереди кадр не запрашивается, начатый — докачивается в кеш', async () => {
  const { loader, calls } = harness({ concurrency: 1 });
  const started = new AbortController();
  const waiting = new AbortController();
  const first = loader.load('/frames/a', started.signal);
  const second = loader.load('/frames/b', waiting.signal);
  await settle();
  waiting.abort();
  await assert.rejects(second, isPreviewAbort);
  started.abort();
  await assert.rejects(first, isPreviewAbort);
  calls[0].resolve();
  await settle();
  assert.deepEqual(
    calls.map((call) => call.url),
    ['/frames/a']
  );
  assert.match(await loader.load('/frames/a'), /^blob:/);
  assert.equal(calls.length, 1);
});

test('Превью: крупный кадр встаёт в начало очереди', async () => {
  const { loader, calls } = harness({ concurrency: 1 });
  void loader.load('/frames/a');
  void loader.load('/frames/b');
  void loader.load('/frames/c', undefined, true);
  await settle();
  calls[0].resolve();
  await settle();
  assert.equal(calls[1].url, '/frames/c');
});

test('Превью: при переполнении вытесняется давно не показанный кадр', async () => {
  const { loader, calls, revoked } = harness({ maxBytes: 25 });
  const load = async (name) => {
    const pending = loader.load('/frames/' + name);
    await settle();
    calls.at(-1).resolve(10);
    return pending;
  };
  const a = await load('a');
  const b = await load('b');
  assert.equal(await loader.load('/frames/a'), a);
  await load('c');
  assert.deepEqual(revoked, [b]);
  assert.equal(await loader.load('/frames/a'), a);
  assert.equal(calls.length, 3);
});

test('Превью: очистка при смене сессии отзывает кеш и отменяет запросы', async () => {
  const { loader, calls, revoked } = harness();
  const cached = loader.load('/frames/a');
  await settle();
  calls[0].resolve();
  const url = await cached;
  const inFlight = loader.load('/frames/b');
  await settle();
  loader.clear();
  assert.deepEqual(revoked, [url]);
  await assert.rejects(inFlight, isPreviewAbort);
  assert.equal(calls[1].signal.aborted, true);
  calls[1].resolve();
  void loader.load('/frames/a');
  await settle();
  assert.equal(calls.length, 3);
});
