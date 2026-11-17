import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canSupplementInstitution,
  institutionArchiveError,
  institutionVersionTitle,
  latestInstitutionArchive
} from '../../src/modules/morefoto/production/live/institution-archive-rules.ts';

const version = (number, status = 'ready', kind = 'full') => ({
  id: 'v' + number,
  number,
  status,
  kind
});
const archive = (patch = {}) => ({
  state: 'ready',
  canManage: true,
  outsideLines: 0,
  blockers: [],
  versions: [version(1)],
  ...patch
});

test('a new open shoot keeps the previous complete archive downloadable', () => {
  const output = archive({
    state: 'open',
    blockers: [{ id: 'new-group' }],
    versions: [version(1), version(2, 'pending')]
  });
  assert.equal(latestInstitutionArchive(output)?.id, 'v1');
  assert.equal(canSupplementInstitution(output), false);
  output.versions[1].status = 'ready';
  assert.equal(latestInstitutionArchive(output)?.id, 'v2');
});

test('supplements and failed releases do not replace a complete downloadable archive', () => {
  assert.equal(
    latestInstitutionArchive(
      archive({
        versions: [version(1), version(2, 'ready', 'supplement'), version(3, 'failed')]
      })
    )?.id,
    'v1'
  );
  assert.equal(latestInstitutionArchive(archive({ versions: [version(1, 'expired')] })), null);
  assert.equal(latestInstitutionArchive(archive({ versions: [] })), null);
});

test('only the organizer supplements new timely lines after the full scope has closed and waited', () => {
  assert.equal(canSupplementInstitution(archive({ outsideLines: 1 })), true);
  for (const patch of [{ canManage: false }, { state: 'scheduled' }, { blockers: [{ id: 'open' }] }, { versions: [] }]) {
    assert.equal(canSupplementInstitution(archive({ outsideLines: 1, ...patch })), false);
  }
  assert.equal(canSupplementInstitution(archive()), false);
});

test('full cumulative releases and new-only supplements are named separately', () => {
  assert.equal(institutionVersionTitle(version(3)), 'Полный архив · версия 3');
  assert.equal(institutionVersionTitle(version(4, 'ready', 'supplement')), 'Дополнение · версия 4');
});

test('scope changes, expiry and retries have actionable messages', () => {
  assert.match(institutionArchiveError('PRINT_SALES_OPEN'), /Прежний готовый архив остаётся доступен/);
  assert.match(institutionArchiveError('COMPOSITION_CHANGED'), /автоматический планировщик/);
  assert.match(institutionArchiveError('ARCHIVE_MISSING'), /сохранённый состав/);
  assert.match(institutionArchiveError('OPERATION_IN_PROGRESS'), /через несколько секунд/);
  assert.equal(institutionArchiveError('UNKNOWN'), null);
});
