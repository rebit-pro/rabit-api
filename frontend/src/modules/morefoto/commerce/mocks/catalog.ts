import { readDemo } from '../../mocks/storage';
import type { Catalog } from '../types';
export const defaultCatalog: Catalog = {
  revision: 1,
  giftThreshold: 200000,
  giftForStaff: false,
  products: [
    {
      id: 'print-10x15',
      name: 'Отпечаток 10 × 15',
      description: 'Один отпечаток выбранного кадра на фотобумаге.',
      kind: 'physical',
      price: 18000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'print-15x23',
      name: 'Отпечаток 15 × 23',
      description: 'Увеличенный отпечаток выбранного кадра.',
      kind: 'physical',
      price: 30000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'print-20x30',
      name: 'Отпечаток 20 × 30',
      description: 'Крупный отпечаток на фотобумаге.',
      kind: 'physical',
      price: 60000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'print-30x45',
      name: 'Отпечаток 30 × 45',
      description: 'Большой отпечаток для оформления в рамку.',
      kind: 'physical',
      price: 110000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'pair-10x15',
      name: 'Два отпечатка 10 × 15',
      description: 'Один комплект — два одинаковых отпечатка выбранного кадра.',
      kind: 'physical',
      price: 28000,
      printCount: 2,
      staffDiscount: true,
      active: true
    },
    {
      id: 'canvas-30x45',
      name: 'Холст 30 × 45',
      description: 'Один холст с выбранной фотографией.',
      kind: 'physical',
      price: 240000,
      printCount: 1,
      staffDiscount: false,
      active: true
    },
    {
      id: 'calendar-30x45',
      name: 'Календарь 30 × 45',
      description: 'Один календарь с выбранным кадром. Макет согласуется перед печатью.',
      kind: 'physical',
      price: 120000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'magnet-10x15',
      name: 'Магнит 10 × 15',
      description: 'Один фотомагнит с выбранным кадром.',
      kind: 'physical',
      price: 35000,
      printCount: 1,
      staffDiscount: true,
      active: true
    },
    {
      id: 'digital',
      name: 'Электронный кадр',
      description: 'Один файл выбранного снимка. Повторное скачивание в период доступа бесплатно.',
      kind: 'digital',
      price: 25000,
      printCount: 0,
      staffDiscount: true,
      active: true
    },
    {
      id: 'bundle',
      name: 'Все электронные кадры ребёнка',
      description: 'Полный комплект только этой серии в текущей съёмке. Отдельные файлы не оплачиваются повторно.',
      kind: 'bundle',
      price: 100000,
      printCount: 0,
      staffDiscount: true,
      active: true
    }
  ]
};
export function getCatalog(groupId?: string): Catalog {
  const catalog = readDemo<Catalog>('catalog:v1', defaultCatalog);
  const condition = groupId
    ? readDemo<Record<string, import('../../management/types').GroupConditions>>('group-conditions:v1', {})[groupId]
    : undefined;
  if (!condition) return catalog;
  if (condition.inherit) return { ...catalog, conditionsRevision: condition.revision };
  return {
    ...catalog,
    conditionsRevision: condition.revision,
    giftThreshold: condition.giftThreshold,
    giftForStaff: condition.giftForStaff,
    products: catalog.products.map((product) => {
      const override = condition.products[product.id];
      return {
        ...product,
        price: override?.price ?? product.price,
        staffDiscount: override?.staffDiscount ?? product.staffDiscount,
        active: product.active && !!override?.active
      };
    })
  };
}
