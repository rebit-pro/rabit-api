import type { GalleryChild } from './types';

export function sortGalleryChildren(children: GalleryChild[]): GalleryChild[] {
  return [...children].sort((a, b) => a.code.length - b.code.length || a.code.localeCompare(b.code, 'en'));
}
