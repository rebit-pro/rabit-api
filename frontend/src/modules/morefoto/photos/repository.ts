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
          assignments: [{ childId: groupId + ':' + child.code, childCode: child.code, sequence: index + 1, code: photo.code }],
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
  const state = readDemo(photoStateKey, initialState());
  state.photos.forEach((photo) => {
    photo.assignments ??= photo.childCode
      ? [{ childId: photo.groupId + ':' + photo.childCode, childCode: photo.childCode, sequence: photo.sequence ?? 1, code: photo.code }]
      : [];
  });
  return state;
}
export function writePhotos(state: PhotoState): void {
  writeDemo(photoStateKey, state);
  window.dispatchEvent(new Event(photosChangedEvent));
}
export function groupChildren(groupId: string): GalleryChild[] {
  const photos = readPhotos().photos.filter((item) => item.groupId === groupId);
  const codes = [...new Set(photos.flatMap((item) => item.assignments.map((assignment) => assignment.childCode)))].sort();
  return codes.map((code) => ({
    code,
    photos: photos
      .flatMap((photo) => {
        const assignment = photo.assignments.find((item) => item.childCode === code);
        return assignment ? [{ photo, assignment }] : [];
      })
      .sort((a, b) => a.assignment.sequence - b.assignment.sequence)
      .map(({ photo, assignment }) => ({
        id: photo.id,
        code: assignment.code,
        thumbSrc: photo.thumbSrc,
        previewSrc: photo.previewSrc,
        width: photo.width,
        height: photo.height
      }))
  }));
}
