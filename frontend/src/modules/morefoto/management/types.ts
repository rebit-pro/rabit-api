import type { Product, ProductKind } from '../commerce/types.js';
import type { StaffRole } from '../types.js';
export interface ManagedStaff {
  id: number;
  name: string;
  email: string;
  role: StaffRole;
  active: boolean;
  revision: number;
  accessRevision: number;
}
export interface GroupConditions {
  revision: number;
  inherit: boolean;
  products: Record<string, { price: number; active: boolean; staffDiscount: boolean }>;
  giftThreshold: number;
  giftForStaff: boolean;
}
export interface CommandBase {
  requestId: string;
}
export interface ProductCommand extends CommandBase {
  kind: 'product';
  revision: number;
  product: Omit<Product, 'price' | 'printCount'> & { price: string; printCount: string; format: string; unit: string };
}
export interface ConditionsCommand extends CommandBase {
  kind: 'conditions';
  revision: number;
  conditionsRevision: number;
  groupId: string | null;
  shootId: string | null;
  institutionId: string | null;
  inherit: boolean;
  products: { id: string; name: string; kind: ProductKind; price: string; active: boolean; staffDiscount: boolean }[];
  giftEnabled: boolean;
  giftThreshold: string;
  giftForStaff: boolean;
}
export interface UserCommand extends CommandBase {
  kind: 'user';
  id: number | null;
  revision: number | null;
  assignmentSignature: string;
  name: string;
  email: string;
  role: StaffRole;
  active: boolean;
  institutionIds: string[];
  groupIds: string[];
  replaceAssignments: boolean;
}
export type ManagementCommand = ProductCommand | ConditionsCommand | UserCommand;
export type ManagementErrors = Record<string, string>;
