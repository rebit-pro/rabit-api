import { galleryChildren } from '../gallery/mocks/photos';
import { readOrganization } from '../organization/repository';
import { readDemo, writeDemo } from '../mocks/storage';
import type { GalleryChild } from '../gallery/types';
import type { ManagedPhoto, PhotoState } from './types';
export const photoStateKey = 'photos:v1';
export const photosChangedEvent = 'morefoto:photos:changed';
function initialState(): PhotoState {
  const groups = readOrganization().groups;
  const photos: ManagedPhoto[] = [];
  for (const [groupId, children] of Object.entries(galleryChildren)) {
    const group = groups.find((item) => item.id === groupId);
    if (!group) continue;
    for (const child of children)
      child.photos.forEach((photo, index) =>
        photos.push({
          ...photo,
          shootId: group.shootId,
          groupId,
          originalGroupId: groupId,
          childCode: child.code,
          sequence: index + 1,
          filename: photo.code + '.webp',
          bytes: 0,
          fingerprint: 'seed:' + photo.previewSrc,
          source: 'seed',
          revision: 1
        })
      );
  }
  return { photos, covers: {} };
}
export function readPhotos(): PhotoState {
  return readDemo(photoStateKey, initialState());
}
export function writePhotos(state: PhotoState): void {
  writeDemo(photoStateKey, state);
  window.dispatchEvent(new Event(photosChangedEvent));
}
export function groupChildren(groupId: string): GalleryChild[] {
  const photos = readPhotos().photos.filter((item) => item.groupId === groupId && item.childCode);
  return [...new Set(photos.map((item) => item.childCode!))].sort().map((code) => ({
    code,
    photos: photos
      .filter((item) => item.childCode === code)
      .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
      .map(({ id, code: photoCode, thumbSrc, previewSrc, width, height }) => ({ id, code: photoCode, thumbSrc, previewSrc, width, height }))
  }));
}
