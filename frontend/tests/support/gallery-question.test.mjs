import test from 'node:test';
import assert from 'node:assert/strict';
import { createRenderer, ref } from 'vue';
import { useGalleryQuestion } from '../../src/modules/morefoto/support/composables/useGalleryQuestion.ts';
import { pendingStorageKey, questionStorageKey, seenStorageKey } from '../../src/modules/morefoto/support/rules.ts';

/** Browser storage and page visibility the composable touches; the tests run in Node. */
const storage = new Map();
for (const [name, value] of Object.entries({
  localStorage: {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, String(value)),
    removeItem: (key) => storage.delete(key)
  },
  document: { visibilityState: 'visible', addEventListener() {}, removeEventListener() {} }
})) {
  Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
}

/** A renderer without DOM: enough to run setup, mount and unmount hooks of a real component. */
const { createApp } = createRenderer({
  createElement: () => ({}),
  createText: () => ({}),
  createComment: () => ({}),
  insert() {},
  remove() {},
  setText() {},
  setElementText() {},
  patchProp() {},
  parentNode: () => null,
  nextSibling: () => null
});

/** Every server call waits until the test answers it, so a late answer can arrive after the gallery changed. */
function server() {
  const calls = [];
  const call =
    (method) =>
    (...args) =>
      new Promise((resolve, reject) => calls.push({ method, args, resolve, reject }));
  return { api: { ask: call('ask'), current: call('current'), add: call('add') }, calls };
}

function mount(token, api) {
  const open = ref(false);
  let questions;
  const app = createApp({
    setup() {
      questions = useGalleryQuestion(token, open, api);
      return () => null;
    }
  });
  app.mount({});
  return { questions, unmount: () => app.unmount() };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));
const galleryA = 'gallery-a';
const galleryB = 'gallery-b';
const keyA = 'a'.repeat(64);
const keyB = 'b'.repeat(64);
const conversation = (text, replyId = 0) => ({
  id: 1,
  number: 1,
  messages: [
    { id: 1, author: 'parent', authorName: 'Мария', text, createdAt: '2026-09-26T10:00:00Z', delivery: 'delivered' },
    ...(replyId
      ? [{ id: replyId, author: 'curator', authorName: 'Анна', text: 'Ответ', createdAt: '2026-09-26T10:05:00Z', delivery: null }]
      : [])
  ]
});

test.beforeEach(() => storage.clear());

test('after a switch to another gallery the parent continues that gallery’s conversation, not the previous one', async () => {
  storage.set(questionStorageKey(galleryA), keyA);
  storage.set(seenStorageKey(galleryA), '5');
  storage.set(questionStorageKey(galleryB), keyB);
  const { api, calls } = server();
  const token = ref(galleryA);
  const { questions, unmount } = mount(token, api);

  assert.deepEqual(calls[0].args, [keyA], 'the quiet check on opening reads gallery A');
  calls[0].resolve(conversation('Вопрос A', 7));
  await settle();
  assert.equal(questions.unread.value, true, 'A has an unread reply');

  token.value = galleryB;
  assert.equal(questions.question.value, null, 'A’s history is not shown in B');
  assert.equal(questions.unread.value, false);
  assert.equal(questions.needsName.value, false, 'B has its own conversation');
  assert.deepEqual([calls[1].method, calls[1].args], ['current', [keyB]], 'the quiet check reads gallery B');
  calls[1].resolve(conversation('Вопрос B'));
  await settle();

  const sent = questions.send('Ещё вопрос');
  assert.deepEqual([calls[2].method, calls[2].args.slice(0, 2)], ['add', [keyB, 'Ещё вопрос']]);
  calls[2].resolve(conversation('Вопрос B'));
  assert.equal(await sent, true);
  assert.ok(
    calls.slice(1).every(({ args }) => !args.includes(keyA)),
    'nothing in B reads or continues A'
  );
  assert.equal(storage.get(questionStorageKey(galleryA)), keyA);
  assert.equal(storage.get(seenStorageKey(galleryA)), '5');
  unmount();
});

test('the history of the previous gallery arriving late does not replace the new gallery’s', async () => {
  storage.set(questionStorageKey(galleryA), keyA);
  storage.set(questionStorageKey(galleryB), keyB);
  const { api, calls } = server();
  const token = ref(galleryA);
  const { questions, unmount } = mount(token, api);

  token.value = galleryB;
  calls[0].resolve(conversation('Вопрос A', 7));
  await settle();
  assert.equal(questions.question.value, null);

  calls[1].resolve(conversation('Вопрос B'));
  await settle();
  assert.equal(questions.question.value.messages[0].text, 'Вопрос B');
  unmount();
});

test('a first question answered after the switch stays with its gallery and does not touch the new one', async () => {
  const { api, calls } = server();
  const token = ref(galleryA);
  const { questions, unmount } = mount(token, api);
  assert.equal(calls.length, 0, 'no conversation yet');

  questions.name.value = 'Мария';
  const sent = questions.send('Когда фото?');
  assert.deepEqual([calls[0].method, calls[0].args.slice(0, 3)], ['ask', [galleryA, 'Мария', 'Когда фото?']]);
  assert.equal(questions.pending.value, true);
  assert.notEqual(storage.get(pendingStorageKey(galleryA)), undefined);

  token.value = galleryB;
  assert.equal(questions.sending.value, false, 'B is not waiting for A');
  assert.equal(questions.pending.value, false);
  assert.equal(questions.needsName.value, true);
  assert.equal(questions.name.value, '');

  calls[0].resolve({ ...conversation('Когда фото?'), questionKey: keyA });
  assert.equal(await sent, false, 'the answer belongs to A and is not shown in B');
  assert.equal(questions.question.value, null);
  assert.equal(questions.needsName.value, true);
  assert.equal(questions.sendError.value, '');
  assert.equal(storage.get(questionStorageKey(galleryA)), keyA, 'A keeps its conversation');
  assert.equal(storage.has(pendingStorageKey(galleryA)), false, 'A’s first question is confirmed');
  assert.equal(storage.has(questionStorageKey(galleryB)), false, 'A’s key is not stored under B');

  questions.name.value = 'Олег';
  void questions.send('Вопрос по B');
  assert.deepEqual([calls[1].method, calls[1].args.slice(0, 3)], ['ask', [galleryB, 'Олег', 'Вопрос по B']]);

  token.value = galleryA;
  assert.equal(questions.needsName.value, false, 'back in A the conversation continues');
  assert.deepEqual([calls[2].method, calls[2].args], ['current', [keyA]]);
  unmount();
});

/** What axios rejects with when the server no longer knows the conversation key. */
const notFound = { isAxiosError: true, response: { status: 404, data: { error: { code: 'notFound' } } } };
const keyC = 'c'.repeat(64);

test('a late 404 for the old key keeps the key of the conversation started after the current 404', async () => {
  storage.set(questionStorageKey(galleryA), keyA);
  storage.set(seenStorageKey(galleryA), '5');
  storage.set(questionStorageKey(galleryB), keyB);
  const { api, calls } = server();
  const token = ref(galleryA);
  const { questions, unmount } = mount(token, api);
  assert.deepEqual([calls[0].method, calls[0].args], ['current', [keyA]], 'the first check stays unanswered');

  token.value = galleryB;
  calls[1].resolve(conversation('Вопрос B'));
  token.value = galleryA;
  assert.deepEqual([calls[2].method, calls[2].args], ['current', [keyA]], 'the second check of A');
  calls[2].reject(notFound);
  await settle();
  assert.equal(storage.has(questionStorageKey(galleryA)), false, 'the current 404 forgets the lost conversation');
  assert.equal(questions.needsName.value, true);

  questions.name.value = 'Мария';
  const sent = questions.send('Новый вопрос');
  assert.deepEqual([calls[3].method, calls[3].args.slice(0, 3)], ['ask', [galleryA, 'Мария', 'Новый вопрос']]);
  calls[3].resolve({ ...conversation('Новый вопрос'), questionKey: keyC });
  assert.equal(await sent, true);
  assert.equal(storage.get(questionStorageKey(galleryA)), keyC);

  calls[0].reject(notFound);
  await settle();
  assert.equal(storage.get(questionStorageKey(galleryA)), keyC, 'the late 404 does not erase the new key');
  assert.equal(questions.needsName.value, false, 'the new conversation continues');
  assert.equal(questions.question.value.messages[0].text, 'Новый вопрос');
  assert.equal(storage.has(pendingStorageKey(galleryA)), false);
  assert.equal(storage.get(seenStorageKey(galleryA)), '5');
  assert.equal(storage.get(questionStorageKey(galleryB)), keyB, 'the other gallery keeps its conversation');

  void questions.send('Ещё вопрос');
  assert.deepEqual([calls[4].method, calls[4].args.slice(0, 2)], ['add', [keyC, 'Ещё вопрос']]);
  unmount();
});

test('a 404 for the old key keeps a key another tab stored meanwhile', async () => {
  storage.set(questionStorageKey(galleryA), keyA);
  const { api, calls } = server();
  const { unmount } = mount(ref(galleryA), api);

  storage.set(questionStorageKey(galleryA), keyC);
  calls[0].reject(notFound);
  await settle();
  assert.equal(storage.get(questionStorageKey(galleryA)), keyC);
  unmount();
});

test('a 404 for the stored key forgets it and lets the parent start a new conversation', async () => {
  storage.set(questionStorageKey(galleryA), keyA);
  const { api, calls } = server();
  const { questions, unmount } = mount(ref(galleryA), api);

  calls[0].reject(notFound);
  await settle();
  assert.equal(storage.has(questionStorageKey(galleryA)), false);
  assert.equal(questions.needsName.value, true);
  assert.equal(questions.question.value, null);

  questions.name.value = 'Мария';
  void questions.send('Новый вопрос');
  assert.deepEqual([calls[1].method, calls[1].args.slice(0, 3)], ['ask', [galleryA, 'Мария', 'Новый вопрос']]);
  unmount();
});
