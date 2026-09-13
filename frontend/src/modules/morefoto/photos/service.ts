import { isMockApiEnabled } from '@/mocks/config';
import { requireDemoAccount, DemoError } from '../mocks/service';
import { readOrganization } from '../organization/repository';
import { readDemo, writeDemo } from '../mocks/storage';
import { simulateRequest } from '../mocks/runtime';
import { readPhotos, writePhotos } from './repository';
import { duplicatePhoto, nextSequence, photoCode, validChildCode } from './rules';
import { localPhotoSource, writePhotoBlobs } from './blobs';
import type { ManagedPhoto, PreparedPhoto, UploadJob } from './types';

export function requirePhotoOrganizer(token: string) {
  if (!isMockApiEnabled || requireDemoAccount(token).role !== 'organizer')
    throw new DemoError(403, 'Подготовка фотографий доступна организатору.');
}
export function editableGroup(token: string, shootId: string, groupId: string) {
  requirePhotoOrganizer(token);
  const group = readOrganization().groups.find((item) => item.id === groupId && item.shootId === shootId);
  if (!group) throw new DemoError(404, 'Группа не найдена в этой съёмке.');
  const orders = readDemo<{ groupId: string }[]>('orders:v1', []);
  if (group.state !== 'preparing' || orders.some((order) => order.groupId === groupId))
    throw new DemoError(409, 'Подборка уже используется для заказов. Изменения доступны до открытия приёма.');
  return group;
}
async function lock<T>(operation: () => T | Promise<T>): Promise<T> {
  return navigator.locks ? navigator.locks.request('morefoto:photos:write', operation) : operation();
}
export async function acceptPhoto(
  token: string,
  job: UploadJob,
  prepared: PreparedPhoto,
  signal: AbortSignal
): Promise<'done' | 'duplicate'> {
  await simulateRequest();
  if (readDemo('photos:fail-next', false)) {
    writeDemo('photos:fail-next', false);
    throw new Error('Не удалось сохранить файл. Остальные файлы продолжат подготовку; этот можно повторить.');
  }
  return lock(async () => {
    editableGroup(token, job.shootId, job.groupId);
    if (signal.aborted) throw new Error('Подготовка остановлена.');
    const state = readPhotos();
    if (state.photos.some((item) => item.id === job.id)) return 'done';
    const duplicate = duplicatePhoto(state.photos, job.shootId, prepared.fingerprint);
    if (duplicate) {
      if (duplicate.source === 'local' && duplicate.groupId === job.groupId) {
        await writePhotoBlobs(duplicate.id, prepared.thumb, prepared.preview);
        editableGroup(token, job.shootId, job.groupId);
        if (signal.aborted) throw new Error('Подготовка остановлена.');
        writePhotos(state);
      }
      return 'duplicate';
    }
    await writePhotoBlobs(job.id, prepared.thumb, prepared.preview);
    editableGroup(token, job.shootId, job.groupId);
    if (signal.aborted) throw new Error('Подготовка остановлена.');
    const photo: ManagedPhoto = {
      id: job.id,
      shootId: job.shootId,
      groupId: job.groupId,
      originalGroupId: job.groupId,
      childCode: null,
      sequence: null,
      code: '',
      filename: job.filename,
      bytes: job.bytes,
      fingerprint: prepared.fingerprint,
      source: 'local',
      revision: 1,
      width: prepared.width,
      height: prepared.height,
      thumbSrc: localPhotoSource(job.id, 'thumb'),
      previewSrc: localPhotoSource(job.id, 'preview')
    };
    state.photos.push(photo);
    writePhotos(state);
    return 'done';
  });
}
export async function assignPhotos(token: string, shootId: string, groupId: string, ids: string[], child: string) {
  await simulateRequest();
  return lock(() => {
    editableGroup(token, shootId, groupId);
    const code = child.trim().toUpperCase();
    if (!validChildCode(code)) throw new Error('Код ребёнка — от одной до трёх латинских букв: A, B, AA.');
    const state = readPhotos();
    const chosen = state.photos.filter((item) => ids.includes(item.id));
    if (
      !chosen.length ||
      chosen.length !== new Set(ids).size ||
      chosen.some((item) => item.groupId !== groupId || item.shootId !== shootId)
    )
      throw new Error('Выберите кадры одной группы. Обновите список.');
    let sequence = nextSequence(state.photos, groupId, code);
    for (const photo of chosen) {
      if (photo.childCode !== code) {
        photo.childCode = code;
        photo.sequence = sequence++;
        photo.code = photoCode(code, photo.sequence);
        photo.revision++;
      }
    }
    writePhotos(state);
  });
}
export async function chooseCover(token: string, shootId: string, groupId: string, photoId: string) {
  await simulateRequest();
  return lock(() => {
    editableGroup(token, shootId, groupId);
    const state = readPhotos();
    if (!state.photos.some((item) => item.id === photoId && item.groupId === groupId && item.childCode))
      throw new Error('Сначала назначьте кадр ребёнку в этой группе.');
    state.covers[groupId] = photoId;
    writePhotos(state);
  });
}
export async function moveChild(
  token: string,
  shootId: string,
  fromId: string,
  child: string,
  toId: string,
  targetCode: string,
  expectedIds: string[]
) {
  await simulateRequest();
  return lock(() => {
    const from = editableGroup(token, shootId, fromId);
    const to = editableGroup(token, shootId, toId);
    if (fromId === toId) throw new Error('Выберите другую группу.');
    if (from.kind !== to.kind) throw new Error('Перенос в папку сотрудников выполняется после проверки списка сотрудников.');
    const code = targetCode.trim().toUpperCase();
    if (!validChildCode(code)) throw new Error('Укажите буквенный код ребёнка.');
    const state = readPhotos();
    const bundle = state.photos.filter((item) => item.groupId === fromId && item.childCode === child);
    if (!bundle.length || bundle.length !== new Set(expectedIds).size || bundle.some((item) => !expectedIds.includes(item.id)))
      throw new Error('Состав набора изменился. Обновите список и проверьте весь набор.');
    if (state.photos.some((item) => item.groupId === toId && item.childCode === code))
      throw new Error('Этот код уже занят в целевой группе. Выберите свободный код.');
    bundle.forEach((photo, index) => {
      photo.groupId = toId;
      photo.childCode = code;
      photo.sequence = index + 1;
      photo.code = photoCode(code, index + 1);
      photo.revision++;
    });
    if (bundle.some((item) => item.id === state.covers[fromId])) delete state.covers[fromId];
    writePhotos(state);
  });
}
