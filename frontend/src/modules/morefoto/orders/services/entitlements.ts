import type { CartQuote } from '../../commerce/types';
import type { GallerySnapshot } from '../../gallery/types';
export function digitalEntitlements(gallery: GallerySnapshot, quote: CartQuote) {
  return gallery.children.flatMap((child) => {
    const whole =
      quote.gifts.includes(child.code) || quote.lines.some((line) => line.childCode === child.code && line.product.kind === 'bundle');
    return child.photos.filter(
      (photo) => whole || quote.lines.some((line) => line.product.kind === 'digital' && line.photoId === photo.id)
    );
  });
}
