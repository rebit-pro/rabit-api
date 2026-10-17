import test from 'node:test';
import assert from 'node:assert/strict';
import { orderEmailError, orderEmailText, resendWait } from '../../src/modules/morefoto/orders/live/order-email-rules.ts';

// J2/J5: the letters with the originals and the link to the paid order on the order page.
const summary = (patch = {}) => ({
  enabled: true,
  recipient: 'mama@example.test',
  latest: null,
  canResend: true,
  resendAvailableAt: null,
  ...patch
});
const letter = (status, patch = {}) => ({
  id: 'd',
  kind: 'auto',
  status,
  recipient: 'mama@example.test',
  createdAt: '2026-10-15T13:00:00+03:00',
  acceptedAt: null,
  letters: 1,
  acceptedLetters: 0,
  photos: 0,
  linkOnly: 0,
  ...patch
});

test('the block is hidden while sending is off or the order is not paid', () => {
  assert.equal(orderEmailText(summary({ enabled: false })), null);
  assert.equal(orderEmailText(summary({ recipient: null })), null);
});

test('each state of the letter has its own sentence with the address', () => {
  assert.deepEqual(orderEmailText(summary()), { text: 'Можно отправить фото и ссылку на этот заказ на mama@example.test.', tone: 'info' });
  assert.match(orderEmailText(summary({ latest: letter('pending') })).text, /^Отправляем ссылку на заказ на mama@example\.test/);
  const accepted = orderEmailText(summary({ latest: letter('accepted', { acceptedAt: '2026-10-15T10:05:00Z' }) }));
  assert.equal(accepted.tone, 'success');
  assert.match(accepted.text, /отправлена на mama@example\.test 15 октября .*13:05 мск\. Если письма нет — проверьте папку «Спам»\./);
  assert.deepEqual(orderEmailText(summary({ latest: letter('failed') })), {
    text: 'Не удалось отправить письмо на mama@example.test.',
    tone: 'warning'
  });
  assert.equal(orderEmailText(summary({ latest: letter('cancelled') })).tone, 'info');
});

test('J5-T09: the letters with photos tell how many letters and photos went out', () => {
  assert.deepEqual(orderEmailText(summary({ latest: letter('pending', { letters: 0 }) })), {
    text: 'Готовим письмо с заказом на mama@example.test.',
    tone: 'info'
  });
  assert.deepEqual(orderEmailText(summary({ latest: letter('pending', { photos: 1 }) })), {
    text: 'Отправляем 1 фото на mama@example.test.',
    tone: 'info'
  });
  assert.equal(
    orderEmailText(summary({ latest: letter('pending', { letters: 5, photos: 10 }) })).text,
    'Отправляем 10 фото на mama@example.test — 5 писем.'
  );
  const accepted = orderEmailText(
    summary({ latest: letter('accepted', { letters: 2, acceptedLetters: 2, photos: 3, acceptedAt: '2026-10-15T10:05:00Z' }) })
  );
  assert.equal(accepted.tone, 'success');
  assert.match(
    accepted.text,
    /^Фото и ссылка на заказ отправлены на mama@example\.test 15 октября .*13:05 мск — 2 письма, 3 фото\. Если писем нет — проверьте папку «Спам»\.$/
  );
  assert.match(
    orderEmailText(summary({ latest: letter('accepted', { letters: 1, acceptedLetters: 1, photos: 2 }) })).text,
    /^Фото и ссылка на заказ отправлены на mama@example\.test\. Если письма нет/
  );
  assert.deepEqual(orderEmailText(summary({ latest: letter('failed', { letters: 3, acceptedLetters: 2, photos: 5 }) })), {
    text: 'Не удалось отправить 1 из 3 писем на mama@example.test. Фото можно скачать на этой странице.',
    tone: 'warning'
  });
  assert.equal(
    orderEmailText(summary({ latest: letter('pending', { letters: 21, photos: 41, linkOnly: 1 }) })).text,
    'Отправляем 41 фото на mama@example.test — 21 письмо. 1 фото больше 20 МБ — их можно скачать только на этой странице.'
  );
  assert.match(orderEmailText(summary({ latest: letter('pending', { letters: 12, photos: 24 }) })).text, /— 12 писем\.$/);
});

test('the repeat waits for the server interval', () => {
  const at = '2026-10-15T10:10:00Z';
  assert.match(resendWait(summary({ resendAvailableAt: at }), Date.parse('2026-10-15T10:05:00Z')), /^Повторить можно после 13:10$/);
  assert.equal(resendWait(summary({ resendAvailableAt: at }), Date.parse('2026-10-15T10:11:00Z')), '');
  assert.equal(resendWait(summary(), Date.now()), '');
});

test('server refusals become plain sentences', () => {
  assert.match(orderEmailError({ network: true }), /Ответ сервера не получен/);
  assert.match(orderEmailError({ network: false, code: 'DELIVERY_TOO_SOON' }), /недавно/);
  assert.match(orderEmailError({ network: false, code: 'DELIVERY_LIMIT' }), /пять раз/);
  assert.match(orderEmailError({ network: false, code: 'DELIVERY_DISABLED' }), /выключена/);
  assert.match(orderEmailError({ network: false, code: 'OTHER' }), /Попробуйте позже/);
});
