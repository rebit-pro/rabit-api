import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';
import * as vue from 'vue';
import * as archive from '../../src/modules/morefoto/photos/archive.ts';
import * as archiveQueue from '../../src/modules/morefoto/photos/archive-queue.ts';
import * as progress from '../../src/modules/morefoto/photos/archive-progress.ts';
import * as retry from '../../src/modules/morefoto/photos/upload-retry.ts';
import * as rules from '../../src/modules/morefoto/photos/rules.ts';
import { archivePortions, inspectInstitutionArchive, planClass } from '../../src/modules/morefoto/photos/institution-archive.ts';

// Реальные Vue effectScope и очередь; изолированы сеть, чтение файла и регистрация router/browser callbacks.
// Test runner проекта — node:test; отдельный DOM или тестовый сервер не требуются.
function harness(t, options) {
  const listeners = new Map();
  let leave;
  let confirmations = 0;
  let uploads = 0;
  const timers = new Set();
  const oldWindow = Object.getOwnPropertyDescriptor(globalThis, 'window');
  const oldNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
  Object.defineProperty(globalThis, 'window', {
    configurable: true,
    value: {
      setTimeout: (callback, delay) => {
        const timer = setTimeout(callback, delay);
        timers.add(timer);
        return timer;
      },
      clearTimeout,
      addEventListener: (name, listener) => listeners.set(name, listener),
      removeEventListener: (name) => listeners.delete(name),
      confirm: () => {
        confirmations++;
        return false;
      },
      dispatchEvent: () => {}
    }
  });
  Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { onLine: true } });
  const dependencies = {
    vue,
    'vue-router': {
      onBeforeRouteLeave: (callback) => {
        leave = callback;
      }
    },
    axios: { isAxiosError: () => false },
    '@/stores/auth': { useAuthStore: () => ({ user: { id: 'test' } }) },
    '@/mocks/config': { isMockApiEnabled: false },
    '../rules': rules,
    '../prepare': {},
    '../service': {},
    '../api': { photosApi: { upload: async () => ({ id: 'photo-' + ++uploads, status: 'processing', childCodes: ['A'] }) } },
    '../repository': { photosChangedEvent: 'photos-changed' },
    '../archive': archive,
    '../archive-queue': archiveQueue,
    '../archive-progress': progress,
    '../upload-retry': retry,
    '../zip-reader': { zipEntryBlob: async () => new Blob(['test original']) }
  };
  const source = readFileSync(new URL('../../src/modules/morefoto/photos/composables/usePhotoQueue.ts', import.meta.url), 'utf8');
  const code = ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS }
  }).outputText;
  const exports = {};
  new Function('require', 'exports', code)((id) => {
    assert.ok(id in dependencies, id);
    return dependencies[id];
  }, exports);
  const scope = vue.effectScope();
  const queue = scope.run(() => exports.usePhotoQueue('shoot', options));
  t.after(() => {
    scope.stop();
    for (const timer of timers) clearTimeout(timer);
    for (const [name, descriptor] of [
      ['window', oldWindow],
      ['navigator', oldNavigator]
    ]) {
      if (descriptor) Object.defineProperty(globalThis, name, descriptor);
      else delete globalThis[name];
    }
    assert.equal(listeners.size, 0);
  });
  return {
    queue,
    leave: () => leave(),
    confirmations: () => confirmations,
    unload: () => {
      const event = new Event('beforeunload', { cancelable: true });
      // У BeforeUnloadEvent поле записываемое; у обычного Node 22 Event есть только getter.
      Object.defineProperty(event, 'returnValue', { value: '', writable: true });
      listeners.get('beforeunload')(event);
      return event.defaultPrevented;
    }
  };
}

test('TC-14: выход защищён при подтверждении и между партиями, защита снимается после завершения', async (t) => {
  const active = vue.shallowRef(true);
  const { queue, leave, unload, confirmations } = harness(t, { importScope: ':institution:test', pendingImport: () => active.value });
  assert.equal(leave(), false);
  assert.equal(unload(), true);
  assert.equal(confirmations(), 1);
  const entries = Array.from({ length: 201 }, (_, index) => ({
    path: '1A/A/' + index + '.jpg',
    directory: false,
    bytes: 16,
    method: 0,
    encrypted: false
  }));
  const plan = planClass(inspectInstitutionArchive(entries, 'zip', '').groups[0], new Map());
  const portions = [...archivePortions(plan, 200)];
  queue.appendPortion(portions[0], new File(['zip'], 'school.zip'), { key: 'zip', name: 'school.zip', entries }, 'group');
  await queue.start();
  assert.equal(queue.busy.value, false);
  assert.equal(queue.waiting.value, 200);
  assert.equal(portions[1].files, 1);
  assert.equal(leave(), false);
  assert.equal(unload(), true);
  // Даже при повторе одного файла после завершения мастера очередь ждёт превью до снятия защиты.
  active.value = false;
  assert.equal(unload(), true);
  queue.jobs.value = [];
  assert.equal(leave(), true);
  assert.equal(unload(), false);
});

test('TC-12: прежняя очередь без активной отправки не блокирует выход', (t) => {
  const { leave, unload, confirmations } = harness(t, {});
  assert.equal(leave(), true);
  assert.equal(unload(), false);
  assert.equal(confirmations(), 0);
});
