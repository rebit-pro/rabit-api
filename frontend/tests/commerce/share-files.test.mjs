import test from 'node:test';
import assert from 'node:assert/strict';
import { phoneSaveMode, shareOutcome, sharesPhotos } from '../../src/modules/morefoto/orders/live/share-files.ts';

// #192: «Сохранить в телефон» hands a purchased photo to the phone's share sheet.
const phone = { share: async () => {}, canShare: (data) => data.files?.[0]?.type === 'image/jpeg' };

test('only a touch device whose browser shares image files offers the button', () => {
  assert.equal(sharesPhotos(phone, true), true);
  assert.equal(sharesPhotos(phone, false), false);
  assert.equal(sharesPhotos({ share: phone.share }, true), false);
  assert.equal(sharesPhotos({ ...phone, canShare: () => false }, true), false);
  assert.equal(
    sharesPhotos(
      {
        ...phone,
        canShare: () => {
          throw new TypeError('files');
        }
      },
      true
    ),
    false
  );
  assert.equal(sharesPhotos(undefined, true), false);
});

test('a closed sheet is not an error, an expired tap asks for one more tap', () => {
  assert.equal(shareOutcome(new DOMException('closed', 'AbortError')), 'cancelled');
  assert.equal(shareOutcome(new DOMException('no gesture', 'NotAllowedError')), 'retry');
  assert.equal(shareOutcome(new TypeError('bad data')), 'failed');
  assert.equal(shareOutcome('nope'), 'failed');
});

test('#204: an iPhone saves from the share sheet, Android downloads, a computer only downloads', () => {
  const android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';
  const iphone =
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
  assert.equal(phoneSaveMode(phone, true, android), 'download');
  assert.equal(phoneSaveMode(undefined, true, android), 'download');
  assert.equal(phoneSaveMode(phone, true, iphone), 'share');
  assert.equal(phoneSaveMode({ share: phone.share }, true, iphone), null);
  assert.equal(phoneSaveMode(phone, false, android), null);
  assert.equal(phoneSaveMode(phone, false, iphone), null);
});
