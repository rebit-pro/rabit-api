import test from 'node:test';
import assert from 'node:assert/strict';
import { shareOutcome, sharesPhotos } from '../../src/modules/morefoto/orders/live/share-files.ts';

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
