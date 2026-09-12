import type { ReceiptChannel } from '../types.js';
export interface DeliveryMessage {
  channel: ReceiptChannel;
  status: 'sent' | 'failed';
  at: string;
}
export interface BuyerDelivery {
  receipt: DeliveryMessage;
  filesEmail?: DeliveryMessage;
}
import type { SupportEvent } from '../../curator/types.js';
export type SupportTopic = 'extension' | 'files' | 'receipt' | 'payment' | 'correction' | 'other';
export interface SupportDraft {
  requestId: string;
  topic: SupportTopic;
  photoId: string;
  replyEmail: string;
  message: string;
}
export type SupportErrors = Partial<Record<keyof SupportDraft, string>>;
export interface SupportRequest extends SupportDraft {
  id: string;
  number: string;
  orderId: string;
  orderNumber: string;
  groupId: string;
  groupName: string;
  shootName: string;
  photoCode?: string;
  createdAt: string;
  curator: string;
  revision?: number;
  history?: SupportEvent[];
  status: 'received' | 'in-progress' | 'resolved';
}
