import type { GalleryPhoto } from '../gallery/types.js';
export type ProductKind = 'physical' | 'digital' | 'bundle';
export interface Product {
  id: string;
  name: string;
  description: string;
  kind: ProductKind;
  price: number;
  printCount: number;
  format?: string;
  unit?: string;
  staffDiscount: boolean;
  active: boolean;
}
export interface Catalog {
  products: Product[];
  giftThreshold: number;
  giftForStaff: boolean;
  revision: number;
  conditionsRevision?: number;
}
export interface CartLine {
  assignmentId?: string;
  id: string;
  childCode: string;
  photoId: string | null;
  productId: string;
  quantity: number;
}
export interface CartQuoteLine extends CartLine {
  product: Product;
  photo: GalleryPhoto | null;
  unitPrice: number;
  total: number;
  discount: number;
  coveredByGift: boolean;
}
export interface CartQuote {
  lines: CartQuoteLine[];
  total: number;
  subtotal: number;
  discount: number;
  giftSaving: number;
  gifts: string[];
  count: number;
  invalid: string[];
  revision: number;
  conditionsRevision?: number;
}
