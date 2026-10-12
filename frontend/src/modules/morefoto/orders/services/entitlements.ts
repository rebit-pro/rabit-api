import type { CartQuote } from '../../commerce/types';
import type { GallerySnapshot } from '../../gallery/types';
export function digitalEntitlements(gallery: GallerySnapshot, quote: CartQuote) {
  return gallery.children.flatMap((child) => {
    const whole =
      quote.gifts.includes(child.code) || quote.lines.some((line) => line.childCode === child.code && line.product.kind === 'bundle');
    return child.photos.filter(
      // J4: a print gives the digital copy of its own frame, like a digital line.
      (photo) => whole || quote.lines.some((line) => line.product.kind !== 'bundle' && line.photoId === photo.id)
    );
  });
}
