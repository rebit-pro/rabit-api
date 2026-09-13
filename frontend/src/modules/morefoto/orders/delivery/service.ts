import { availablePhotos, currentBuyer, settlement } from '../../settlement/rules';
import { localPhotoKey, readPhotoBlob } from '../../photos/blobs';
import { getDemoNow } from '../../mocks/clock';
import { readDemo, writeDemo } from '../../mocks/storage';
import { simulateRequest } from '../../mocks/runtime';
import { checkoutCapabilities } from '../services/checkout';
import { resolveOrder, randomKey, saveOrder, withOrderLock } from '../services/orders';
import type { OrderSnapshot, ReceiptChannel } from '../types';
import { orderPeriod } from '../../curator/period';
import { downloadAccess, supportPhotos, validateSupport } from './rules';
import { storedZip } from './zip';
import type { BuyerDelivery, SupportDraft, SupportErrors, SupportRequest } from './types';

export function deliveryFor(order: OrderSnapshot): BuyerDelivery | null {
  if (order.paymentStatus !== 'paid' || !order.paidAt) return null;
  return (
    order.buyerDelivery ?? {
      receipt: {
        channel: order.buyer.receiptChannel,
        status: 'sent',
        at: order.paidAt
      },
      ...(order.digitalPhotos.length && !order.latePayment
        ? {
            filesEmail: {
              channel: 'email' as const,
              status: 'sent' as const,
              at: order.paidAt
            }
          }
        : {})
    }
  );
}
function downloadableOrder(accessKey: string) {
  const order = resolveOrder(accessKey);
  const access = downloadAccess(order, getDemoNow());
  if (access.state !== 'available') throw new Error('Скачивание сейчас недоступно. Проверьте срок и статус заказа или обратитесь к Рите.');
  return order;
}
export async function prepareDownload(accessKey: string, photoId?: string, signal?: AbortSignal) {
  await simulateRequest();
  const order = downloadableOrder(accessKey);
  const photos = availablePhotos(order).filter((photo) => !photoId || photo.id === photoId);
  if (!photos.length) throw new Error('Фотография не входит в электронный комплект этого заказа.');
  const files = [];
  for (const photo of photos) {
    let blob: Blob;
    if (localPhotoKey(photo.previewSrc)) {
      blob = await readPhotoBlob(photo.previewSrc);
    } else {
      // Only approved static previews and locally prepared watermarked previews are served.
      if (!/^\/demo\/gallery-v1\/[a-z0-9-]+\/[a-f0-9]+-preview\.webp$/.test(photo.previewSrc))
        throw new Error('Тестовый файл недоступен. Обратитесь к куратору.');
      const response = await fetch(photo.previewSrc, { signal });
      if (!response.ok) throw new Error('Не удалось получить файл. Повторите скачивание.');
      blob = await response.blob();
    }
    if (blob.type !== 'image/webp') throw new Error('Не удалось получить файл. Повторите скачивание.');
    files.push({ name: photo.code + '-demo.webp', data: new Uint8Array(await blob.arrayBuffer()) });
  }
  // The deadline or order may have changed while the files were being prepared.
  const current = downloadableOrder(accessKey);
  if (!photos.every((photo) => availablePhotos(current).some((item) => item.id === photo.id)))
    throw new Error('Состав файлов изменился. Обновите заказ.');
  if (signal?.aborted) throw new Error('Скачивание отменено.');
  const first = files[0];
  if (!first) throw new Error('В заказе нет файлов для скачивания.');
  await withOrderLock(() => {
    const latest = downloadableOrder(accessKey);
    if (signal?.aborted || !photos.every((p) => availablePhotos(latest).some((v) => v.id === p.id)))
      throw new Error('Состав файлов изменился. Обновите заказ.');
    const state = settlement(latest);
    state.preparedFiles.push({ id: randomKey(), at: getDemoNow(), photoIds: photos.map((p) => p.id) });
    state.revision++;
    saveOrder({ ...latest, settlement: state });
  });
  return photoId
    ? {
        name: first.name,
        blob: new Blob([first.data], { type: 'image/webp' })
      }
    : { name: order.number + '-demo.zip', blob: storedZip(files) };
}
export async function resendDelivery(accessKey: string, target: 'receipt' | 'filesEmail', channel: ReceiptChannel) {
  await simulateRequest();
  const failed = await withOrderLock(() => {
    const order = resolveOrder(accessKey);
    const delivery = deliveryFor(order);
    if (!delivery) throw new Error('Дождитесь подтверждения оплаты.');
    if (target === 'filesEmail') downloadableOrder(accessKey);
    if (channel === 'max' && (target !== 'receipt' || !checkoutCapabilities().maxAvailable))
      throw new Error('MAX сейчас недоступен. Выберите email.');
    const failure = readDemo('delivery:fail-once:' + target, false);
    writeDemo('delivery:fail-once:' + target, false);
    saveOrder({
      ...order,
      buyerDelivery: {
        ...delivery,
        [target]: {
          channel,
          status: failure ? 'failed' : 'sent',
          at: getDemoNow()
        }
      }
    });
    return failure;
  });
  if (failed) throw new Error('Доставка не удалась. Повторите отправку или оставьте обращение куратору.');
}
export function readSupportDraft(order: OrderSnapshot): SupportDraft {
  const value = readDemo<Partial<SupportDraft>>('support:draft:' + order.id, {});
  return {
    requestId: typeof value.requestId === 'string' && /^[a-f0-9]{32}$/.test(value.requestId) ? value.requestId : randomKey(),
    topic: value.topic ?? 'files',
    photoId: typeof value.photoId === 'string' ? value.photoId : '',
    replyEmail: typeof value.replyEmail === 'string' ? value.replyEmail : currentBuyer(order).email,
    message: typeof value.message === 'string' ? value.message : ''
  };
}
export class SupportValidationError extends Error {
  constructor(public fields: SupportErrors) {
    super('Проверьте поля обращения.');
  }
}
export async function createSupportRequest(accessKey: string, draft: SupportDraft): Promise<SupportRequest> {
  await simulateRequest();
  const request = await withOrderLock(() => {
    const order = resolveOrder(accessKey);
    const previous = order.supportRequests?.find((item) => item.requestId === draft.requestId);
    if (previous) return previous;
    const errors = validateSupport(draft, order);
    if (Object.keys(errors).length) throw new SupportValidationError(errors);
    if (!/^[a-f0-9]{32}$/.test(draft.requestId)) throw new Error('Обновите форму обращения.');
    const request: SupportRequest = {
      ...draft,
      message: draft.message.trim(),
      replyEmail: draft.replyEmail.trim().toLowerCase(),
      id: randomKey(),
      number: order.number + '-H' + String((order.supportRequests?.length ?? 0) + 1).padStart(2, '0'),
      orderId: order.id,
      orderNumber: order.number,
      groupId: order.groupId,
      groupName: order.groupName,
      shootName: order.shootName,
      photoCode: supportPhotos(order).find((photo) => photo.id === draft.photoId)?.code,
      createdAt: getDemoNow(),
      curator: orderPeriod(order.groupId).curator,
      status: 'received'
    };
    saveOrder({
      ...order,
      supportRequests: [...(order.supportRequests ?? []), request]
    });
    return request;
  });
  if (readDemo('support:lose-response-once', false)) {
    writeDemo('support:lose-response-once', false);
    throw new Error('Ответ не получен. Повторите отправку: сохранённое обращение не продублируется.');
  }
  return request;
}
