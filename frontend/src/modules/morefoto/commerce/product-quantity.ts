import type { CartQuote, Product } from './types.js';

type QuantityPolicy = Pick<Product, 'kind' | 'allowDigitalQuantity'>;

export function allowsQuantity(product: QuantityPolicy): boolean {
  return product.kind === 'physical' || (product.kind === 'digital' && product.allowDigitalQuantity === true);
}

export function conflictsWithFile(product: QuantityPolicy, selected: QuantityPolicy): boolean {
  return product.kind === 'digital' && selected.kind === 'digital' && !(product.allowDigitalQuantity && selected.allowDigitalQuantity);
}

export function fileAlreadySelected(quote: Pick<CartQuote, 'lines'>, photoId: string, product: QuantityPolicy): boolean {
  return product.kind === 'digital' && quote.lines.some((line) => line.photoId === photoId && conflictsWithFile(product, line.product));
}

/** Apply today's catalogue to an old selection. Keep the first chosen offer for a protected original. */
export function restoreFileQuantities<T extends { productId: string; quantity: number }>(
  lines: T[],
  products: Product[],
  photoIdFor: (line: T) => string | null | undefined
): T[] {
  const byId = new Map(products.map((product) => [product.id, product]));
  const files = new Map<string, Product>();
  const kept: T[] = [];
  for (const line of lines) {
    const product = byId.get(line.productId);
    if (product?.kind === 'digital' && product.active) {
      const photoId = photoIdFor(line);
      if (photoId) {
        const selected = files.get(photoId);
        if (selected && conflictsWithFile(product, selected)) continue;
        files.set(photoId, product);
      }
      if (!allowsQuantity(product) && line.quantity !== 1) {
        kept.push({ ...line, quantity: 1 });
        continue;
      }
    }
    kept.push(line);
  }
  return kept;
}
