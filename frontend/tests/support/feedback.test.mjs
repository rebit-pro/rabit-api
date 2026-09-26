import test from 'node:test';
import assert from 'node:assert/strict';
import {
  feedbackFieldProblems,
  feedbackPayloadKey,
  feedbackProblemMessage,
  isFeedbackContact
} from '../../src/modules/morefoto/support/feedback.ts';

test('a contact is an email or a phone with at least ten digits, like on the server', () => {
  for (const contact of ['olga@example.com', ' +7 (900) 123-45-67 ', '89001234567'])
    assert.equal(isFeedbackContact(contact), true, contact);
  for (const contact of ['', 'Ольга', '123-45', 'olga@example', '1'.repeat(121)]) assert.equal(isFeedbackContact(contact), false, contact);
});

test('empty and invalid fields are named before any request', () => {
  assert.deepEqual(feedbackFieldProblems({ name: ' ', contact: '', message: '' }), {
    name: 'Укажите, как к вам обращаться',
    contact: 'Укажите телефон или email',
    message: 'Напишите, чем помочь'
  });
  assert.deepEqual(feedbackFieldProblems({ name: 'Ольга', contact: '12345', message: 'Не могу войти' }), {
    contact: 'Проверьте телефон или email'
  });
  assert.deepEqual(feedbackFieldProblems({ name: 'Ольга', contact: 'olga@example.com', message: 'Не могу войти' }), {});
});

test('the payload key ignores outer spaces so a repeat keeps its Idempotency-Key', () => {
  assert.equal(
    feedbackPayloadKey({ name: ' Ольга', contact: 'olga@example.com ', message: 'Текст' }),
    feedbackPayloadKey({ name: 'Ольга', contact: 'olga@example.com', message: ' Текст ' })
  );
});

test('server problems become readable hints', () => {
  assert.match(feedbackProblemMessage({ status: null, code: '', network: true }), /Нет связи/);
  assert.match(feedbackProblemMessage({ status: 429, code: 'RATE_LIMITED', network: false }), /через час/);
  assert.match(feedbackProblemMessage({ status: 422, code: 'INVALID_FEEDBACK_CONTACT', network: false }), /телефон или email/);
  assert.match(feedbackProblemMessage({ status: 500, code: '', network: false }), /Повторите попытку/);
});
