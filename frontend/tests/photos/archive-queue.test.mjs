import { test } from 'node:test';
import assert from 'node:assert/strict';
import { planArchives } from '../../src/modules/morefoto/photos/archive.ts';
import { ArchiveProgress } from '../../src/modules/morefoto/photos/archive-progress.ts';
import { archiveJobs, deliveredForResume } from '../../src/modules/morefoto/photos/archive-queue.ts';
import { QueuePause } from '../../src/modules/morefoto/photos/upload-retry.ts';

const entry = (path) => ({ path, bytes: 1000, compressedBytes: 1000, method: 0, offset: 0, directory: false, encrypted: false });
const plan = planArchives(
  [{ key: 'zip', name: 'shoot.zip', entries: ['A/01.jpg', 'A/02.jpg', 'GROUP-1/g.jpg'].map(entry) }],
  [],
  new Set(),
  { batch: 2000, bytes: 25 * 1024 * 1024 }
);
const target = { shootId: 'shoot', groupId: 'group', archives: new Set(['zip']) };
let sequence = 0;
const id = () => 'job-' + ++sequence;
const memoryStorage = () => {
  const memory = new Map();
  return { getItem: (key) => memory.get(key) ?? null, setItem: (key, value) => memory.set(key, value) };
};
const remember = (progress, job) => {
  if (deliveredForResume(job.status)) progress.mark('group zip', job.entry);
};

test('only ready or duplicate frames count as delivered for a resume', () => {
  assert.deepEqual(['queued', 'uploading', 'processing', 'done', 'duplicate', 'error', 'interrupted'].filter(deliveredForResume), [
    'done',
    'duplicate'
  ]);
});

test('a frame whose preview failed is queued again when the same ZIP is chosen after a reload (review #151)', () => {
  const storage = memoryStorage();
  const progress = new ArchiveProgress(storage, 'k');
  const first = archiveJobs(plan, [], target, (archive, path) => progress.has('group ' + archive, path), id);
  // 01 got its previews, 02 was accepted and then failed on the server.
  const [ready, failed] = first.jobs;
  remember(progress, { ...ready, status: 'processing' });
  remember(progress, { ...ready, status: 'done' });
  remember(progress, { ...failed, status: 'processing' });
  remember(progress, { ...failed, status: 'error' });

  // A new tab: no queue draft, only the browser progress.
  const reloaded = new ArchiveProgress(storage, 'k');
  const again = archiveJobs(plan, [], target, (archive, path) => reloaded.has('group ' + archive, path), id);
  assert.equal(again.skipped, 1);
  assert.deepEqual(
    again.jobs.map((job) => [job.entry, job.status]),
    [
      ['A/02.jpg', 'queued'],
      ['GROUP-1/g.jpg', 'queued']
    ]
  );
});

test('in the same tab a failed job is replaced, delivered ones stay listed and group frames are sent again', () => {
  const current = archiveJobs(plan, [], target, () => false, id).jobs.map((job, index) => ({
    ...job,
    status: ['done', 'error', 'done'][index]
  }));
  const again = archiveJobs(plan, current, target, () => false, id);

  assert.equal(again.skipped, 0);
  assert.deepEqual(
    again.jobs.map((job) => [job.entry, job.status, current.some((item) => item.id === job.id)]),
    [
      ['A/01.jpg', 'done', true],
      ['A/02.jpg', 'queued', false],
      ['GROUP-1/g.jpg', 'queued', false]
    ]
  );
  assert.deepEqual(again.jobs[2].childCodes, ['A']);
});

test('an online event while a worker is still sending resumes the queue once the worker ends (#153)', () => {
  const pause = new QueuePause();
  pause.stop('offline');
  // The network is back, the second request has not finished yet.
  assert.equal(pause.resumable(true, true), false);
  // start().finally: no worker is busy any more and the network is there.
  assert.equal(pause.resumable(true, false), true);
  // Still offline when the worker ends: the later online event resumes.
  assert.equal(pause.resumable(false, false), false);
});

test('a manual pause, an ended session or a handed-over group are not lifted by the network (#153)', () => {
  for (const reason of ['manual', 'session', 'locked']) {
    const before = new QueuePause();
    before.stop('offline');
    before.stop(reason);
    assert.equal(before.resumable(true, false), false, 'offline then ' + reason);
    const after = new QueuePause();
    after.stop(reason);
    after.stop('offline');
    assert.equal(after.resumable(true, false), false, reason + ' then offline');
  }
  const cleared = new QueuePause();
  cleared.stop('manual');
  cleared.clear();
  cleared.stop('offline');
  assert.equal(cleared.resumable(true, false), true);
});
