import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isVolumePart, readZipEntries, zipEntryBlob, ZipFormatError } from '../../src/modules/morefoto/photos/zip-reader.ts';
import { buildZip } from './zip-builder.mjs';

const text = async (blob) => Buffer.from(await blob.arrayBuffer()).toString('utf8');

test('stored and deflated entries come out with their content', async () => {
  const zip = buildZip([
    { name: 'A/', data: '' },
    { name: 'A/A001.jpg', data: 'stored bytes' },
    { name: 'GROUP-1/g.jpg', data: 'deflated bytes '.repeat(50), deflate: true }
  ]);
  const entries = await readZipEntries(zip);

  assert.deepEqual(
    entries.map((entry) => [entry.path, entry.directory, entry.method, entry.bytes]),
    [
      ['A/', true, 0, 0],
      ['A/A001.jpg', false, 0, 12],
      ['GROUP-1/g.jpg', false, 8, 750]
    ]
  );
  assert.equal(await text(await zipEntryBlob(zip, entries[1])), 'stored bytes');
  assert.equal(await text(await zipEntryBlob(zip, entries[2])), 'deflated bytes '.repeat(50));
});

test('names without the UTF-8 flag are read in the Windows Explorer code page (T08)', async () => {
  // «Фото.jpg» in CP866, as the Explorer «Send to compressed folder» writes it.
  const cp866 = Buffer.from([0x94, 0xae, 0xe2, 0xae]);
  const zip = buildZip([
    { name: Buffer.concat([Buffer.from('A/'), cp866, Buffer.from('.jpg')]), data: 'x', utf8: false },
    { name: 'B/Фото.jpg', data: 'y' }
  ]);

  assert.deepEqual(
    (await readZipEntries(zip)).map((entry) => entry.path),
    ['A/Фото.jpg', 'B/Фото.jpg']
  );
});

test('a Zip64 directory is read through its locator (T17)', async () => {
  const zip = buildZip(
    [
      { name: 'A/1.jpg', data: 'one' },
      { name: 'B/2.jpg', data: 'two', deflate: true }
    ],
    { zip64: true }
  );
  const entries = await readZipEntries(zip);

  assert.deepEqual(
    entries.map((entry) => [entry.path, entry.bytes]),
    [
      ['A/1.jpg', 3],
      ['B/2.jpg', 3]
    ]
  );
  assert.equal(await text(await zipEntryBlob(zip, entries[1])), 'two');
});

test('a volume of a split archive and a non-ZIP file are refused with a reason', async () => {
  await assert.rejects(readZipEntries(buildZip([{ name: 'A/1.jpg', data: 'x' }], { disk: 1 })), ZipFormatError);
  await assert.rejects(readZipEntries(new Blob(['not a zip at all'])), /не является ZIP/);
  assert.equal(isVolumePart('158.z01'), true);
  assert.equal(isVolumePart('158.zip.001'), true);
  assert.equal(isVolumePart('158_part01.zip'), false);
});
