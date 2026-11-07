import type { GalleryChild, GalleryPhoto } from './types';

/** Keep each child's frames together, in supplier order; letter codes continue from Z to AA. */
export function galleryPhotos(children: readonly GalleryChild[]): GalleryPhoto[] {
  return [...children]
    .sort((left, right) => left.code.length - right.code.length || left.code.localeCompare(right.code, 'en'))
    .flatMap((child) => child.photos);
}
