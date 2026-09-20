import type { GallerySnapshot } from '../../gallery/types';
import type { CartLine, CartQuote, CartQuoteLine, Catalog } from '../types';
export function calculateQuote(gallery: GallerySnapshot, source: CartLine[], catalog: Catalog): CartQuote {
  const invalid: string[] = [];
  const lines: CartQuoteLine[] = [];
  for (const item of source) {
    const product = catalog.products.find((product) => product.id === item.productId && product.active);
    const child = gallery.children.find((child) => child.code === item.childCode);
    const photo = child?.photos.find((photo) => photo.id === item.photoId) ?? null;
    if (!product || !child || (product.kind !== 'bundle' && !photo)) {
      invalid.push(item.id);
      continue;
    }
    const quantity = product.kind === 'physical' ? item.quantity : 1;
    const unitPrice = gallery.audience === 'staff' && product.staffDiscount ? Math.round(product.price / 2) : product.price;
    const discount = product.price - unitPrice;
    lines.push({
      ...item,
      quantity,
      product,
      photo,
      unitPrice,
      discount: discount * quantity,
      total: unitPrice * quantity,
      coveredByGift: false
    });
  }
  const gifts = gallery.children
    .filter((child) => {
      const printed = lines
        .filter((line) => line.childCode === child.code && line.product.kind === 'physical')
        .reduce((sum, line) => sum + line.total, 0);
      return catalog.giftThreshold > 0 && printed >= catalog.giftThreshold && (gallery.audience !== 'staff' || catalog.giftForStaff);
    })
    .map((child) => child.code);
  let giftSaving = 0;
  for (const line of lines) {
    if (line.product.kind === 'bundle' && gifts.includes(line.childCode)) {
      giftSaving += line.product.price * line.quantity;
      line.unitPrice = line.product.price;
      line.discount = 0;
      line.total = 0;
      line.coveredByGift = true;
    }
  }
  return {
    lines,
    gifts,
    giftSaving,
    invalid,
    revision: catalog.revision,
    ...(catalog.conditionsRevision === undefined ? {} : { conditionsRevision: catalog.conditionsRevision }),
    total: lines.reduce((sum, line) => sum + line.total, 0),
    subtotal: lines.reduce((sum, line) => sum + line.product.price * line.quantity, 0),
    discount: lines.reduce((sum, line) => sum + line.discount, 0),
    count: lines.reduce((sum, line) => sum + line.quantity, 0)
  };
}
