import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bulkNotice, runEach } from '../../src/modules/morefoto/ui/removal.ts';

test('#92: bulk actions go one by one, a refusal keeps its name and does not stop the rest', async () => {
  const calls = [];
  const result = await runEach(
    [
      { id: 'a', name: 'Звёздочки' },
      { id: 'b', name: 'Лучики' },
      { id: 'c', name: 'Сотрудники' }
    ],
    (item) => item.name,
    async (item) => {
      calls.push(item.id);
      if (item.id === 'b') throw new Error('orders');
    },
    () => 'По ней уже есть заказы — удалить нельзя, данные сохраняются для истории заказов и оплат.'
  );
  assert.deepEqual(calls, ['a', 'b', 'c']);
  assert.deepEqual(result, {
    total: 3,
    done: 2,
    failed: [{ name: 'Лучики', reason: 'По ней уже есть заказы — удалить нельзя, данные сохраняются для истории заказов и оплат.' }]
  });
  assert.deepEqual(bulkNotice('Удалено', result, 'Готово.'), {
    tone: 'warning',
    text: 'Удалено: 2 из 3.',
    failures: ['Лучики: По ней уже есть заказы — удалить нельзя, данные сохраняются для истории заказов и оплат.']
  });
});

test('#92: a later request waits for the previous answer, so a shared revision moves from answer to answer', async () => {
  let revision = 1;
  const sent = [];
  await runEach(
    [1, 2, 3],
    String,
    async () => {
      sent.push(revision);
      await new Promise((resolve) => setTimeout(resolve, 1));
      revision++;
    },
    String
  );
  assert.deepEqual(sent, [1, 2, 3]);
});

test('#92: a full success adds its explanation, an empty action reports zero of zero', async () => {
  assert.deepEqual(bulkNotice('Удалено', { total: 2, done: 2, failed: [] }, 'Позиции убраны из каталога.'), {
    tone: 'success',
    text: 'Удалено: 2 из 2. Позиции убраны из каталога.',
    failures: []
  });
  assert.equal(bulkNotice('Снято с продажи', await runEach([], String, async () => {}, String)).text, 'Снято с продажи: 0 из 0.');
});
