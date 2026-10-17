/** G2: receipts of «Чеки от ЮKassa» — the payment receipt and the closing receipt of the print after its handover. */
export type ReceiptKind = 'payment' | 'closing';
export type ReceiptStatus = 'pending' | 'sent' | 'failed';
export type PaymentMode = 'full_prepayment' | 'full_payment';

export interface ReceiptItem {
  description: string;
  quantity: number;
  /** Price of one unit in kopecks. */
  amount: number;
  paymentMode: PaymentMode;
}
export interface Receipt {
  id: string;
  kind: ReceiptKind;
  status: ReceiptStatus;
  amount: number;
  items: ReceiptItem[];
  createdAt: string;
  registeredAt: string | null;
  fiscalDocumentNumber: string | null;
  fiscalStorageNumber: string | null;
  fiscalAttribute: string | null;
  handedAt: string | null;
}
/** PAY-04: the receipts of the order; enabled — the shop sends receipts. */
export interface OrderReceipts {
  enabled: boolean;
  channel: 'email' | null;
  receipts: Receipt[];
}
export type HandoverState = 'waiting' | 'handed' | 'blocked';
export type HandoverBlock = 'no_payment_receipt' | 'payment_receipt_failed';
export interface PrintHandoverOrder {
  orderId: string;
  orderNumber: string;
  childCodes: string[];
  prints: number;
  paidAt: string;
  state: HandoverState;
  handedAt: string | null;
  receiptStatus: ReceiptStatus | null;
  reason: HandoverBlock | null;
}
/** PAY-12/13: the paid print of a group and its handover to the families. */
export interface PrintHandovers {
  groupId: string;
  groupName: string;
  enabled: boolean;
  orders: PrintHandoverOrder[];
  summary: { waiting: number; handed: number; blocked: number };
}
