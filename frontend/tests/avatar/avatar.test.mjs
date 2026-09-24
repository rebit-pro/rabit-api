import { test } from 'node:test';
import assert from 'node:assert/strict';
import { AVATAR_TONES, avatarInitials, avatarSeed, avatarTone } from '../../src/components/avatar/avatar.ts';

test('initials keep the written order of the first two words', () => {
  assert.equal(avatarInitials('Иванова Мария Сергеевна'), 'ИМ');
  assert.equal(avatarInitials('Мария Иванова'), 'МИ');
  assert.equal(avatarInitials('Анна-Мария Петрова'), 'АП');
  assert.equal(avatarInitials('  анна   петрова  '), 'АП');
  assert.equal(avatarInitials('Ёлкина Юлия'), 'ЁЮ');
  assert.equal(avatarInitials('Йода'), 'Й');
});

test('parenthesised notes and punctuation do not become letters', () => {
  assert.equal(avatarInitials('Петров (куратор) Иван'), 'ПИ');
  assert.equal(avatarInitials('«Мария» Иванова'), 'МИ');
});

test('without a name the email local part is used, without both a bullet', () => {
  assert.equal(avatarInitials('', 'ivan@example.invalid'), 'IV');
  assert.equal(avatarInitials(null, 'i.petrov@example.invalid'), 'IP');
  assert.equal(avatarInitials('   ', ''), '•');
  assert.equal(avatarInitials(undefined, undefined), '•');
});

test('tone is stable, in range and independent of the name', () => {
  const seed = avatarSeed(12, 'anna@example.invalid');
  assert.equal(seed, 'staff:12');
  assert.equal(avatarTone(seed), avatarTone('staff:12'));
  assert.ok(avatarTone(seed) >= 0 && avatarTone(seed) < AVATAR_TONES);
  assert.equal(avatarSeed(null, ' Anna@Example.Invalid '), 'email:anna@example.invalid');
  const tones = new Set(Array.from({ length: 64 }, (_, index) => avatarTone('staff:' + index)));
  assert.ok(tones.size >= 6, 'seeds spread over the palette');
});
