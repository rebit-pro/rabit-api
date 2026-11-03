import type { GalleryChild, GalleryPhoto } from './types';

export function filterGalleryPhotos(children: GalleryChild[], value: string): GalleryPhoto[] {
  const code = value.trim().toUpperCase();
  if (!code) return children.flatMap((child) => child.photos);
  const child = children.find((child) => child.code === code);
  if (child) return child.photos;
  return children.flatMap((child) => child.photos.filter((photo) => photo.code === code));
}
