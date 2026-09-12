import type { AxiosAdapter, AxiosRequestConfig, AxiosResponse } from 'axios';
import type { ChatContentType } from '@/api/exchange';
import { mockNetworkDelayMs } from './config';
import {
  confirmPaymentWithMock,
  connectApiWithMock,
  createAdvertisementWithMock,
  createChatScriptWithMock,
  deleteAdvertisementWithMock,
  deleteChatScriptWithMock,
  disconnectApiWithMock,
  getAdvertisementsWithMock,
  getApiStatusWithMock,
  getBalancesWithMock,
  getCashFlowReportWithMock,
  getChatScriptsWithMock,
  getCounterpartyInfoWithMock,
  getCurrenciesWithMock,
  getCurrencyPairsWithMock,
  getOrderBookWithMock,
  getPaymentMethodsWithMock,
  getTradeDetailWithMock,
  getTradeMessagesWithMock,
  getTradesWithMock,
  getTransactionsWithMock,
  loginWithMock,
  logoutWithMock,
  requestRegistrationCodeWithMock,
  resolveUserIdByToken,
  releaseAssetsWithMock,
  sendTradeMessageWithMock,
  syncBalancesWithMock,
  toggleAdvertisementWithMock,
  updateChatScriptWithMock,
  verifyApiWithMock,
  confirmRegistrationWithMock
} from './database';

class MockHttpError extends Error {
  public readonly status: number;

  public constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

type MockUploadedFile = {
  id: number;
  name: string;
  size: number;
  type: string;
  src: string;
  contentType: ChatContentType;
};

let mockUploadedFileId = 1;
const mockUploadedFiles = new Map<number, MockUploadedFile>();

type MockEnvelope<TData> = {
  data: TData | null;
  error?: {
    message: string;
    debug?: Record<string, unknown>;
  };
};

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function normalizeMethod(config: AxiosRequestConfig): string {
  return (config.method ?? 'get').toLowerCase();
}

function normalizePath(config: AxiosRequestConfig): string {
  const rawUrl = config.url ?? '/';
  const normalizedUrl = rawUrl.startsWith('http') ? rawUrl : `https://mock.rebit.local${rawUrl}`;

  return new URL(normalizedUrl).pathname;
}

function normalizeToken(config: AxiosRequestConfig): string | null {
  const authorizationHeader =
    (config.headers as Record<string, string | undefined> | undefined)?.Authorization ??
    (config.headers as Record<string, string | undefined> | undefined)?.authorization ??
    null;

  if (null === authorizationHeader || !authorizationHeader.startsWith('Bearer ')) {
    return null;
  }

  return authorizationHeader.slice('Bearer '.length);
}

function normalizeBody<TBody>(config: AxiosRequestConfig): TBody {
  if (undefined === config.data || null === config.data || '' === config.data) {
    return {} as TBody;
  }

  if ('string' === typeof config.data) {
    return JSON.parse(config.data) as TBody;
  }

  return config.data as TBody;
}

function ok<TData>(data: TData): MockEnvelope<TData> {
  return {
    data
  };
}

function resolveUploadedFileContentType(mimeType: string): ChatContentType {
  if (mimeType.startsWith('image/')) {
    return 'pic';
  }

  if ('application/pdf' === mimeType) {
    return 'pdf';
  }

  if (mimeType.startsWith('video/')) {
    return 'video';
  }

  return 'str';
}

async function blobToDataUrl(blob: Blob): Promise<string> {
  return await new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onload = () => {
      if ('string' !== typeof reader.result) {
        reject(new Error('Не удалось прочитать файл.'));
        return;
      }

      resolve(reader.result);
    };

    reader.onerror = () => {
      reject(new Error('Не удалось прочитать файл.'));
    };

    reader.readAsDataURL(blob);
  });
}

function getRouteId(path: string, pattern: RegExp): number {
  const matches = path.match(pattern);

  if (null === matches) {
    throw new MockHttpError(404, 'Маршрут не найден.');
  }

  return Number(matches[1]);
}

function ensureAuthorized(config: AxiosRequestConfig): number {
  const userId = resolveUserIdByToken(normalizeToken(config));

  if (null === userId) {
    throw new MockHttpError(401, 'Требуется авторизация.');
  }

  return userId;
}

async function handleMockRequest(config: AxiosRequestConfig): Promise<MockEnvelope<unknown>> {
  const method = normalizeMethod(config);
  const path = normalizePath(config);

  if ('post' === method && '/api/v1/auth/login' === path) {
    return ok(loginWithMock(normalizeBody(config)));
  }

  if ('post' === method && '/api/v1/auth/register/request-code' === path) {
    return ok(requestRegistrationCodeWithMock(normalizeBody(config)));
  }

  if ('post' === method && '/api/v1/auth/register/confirm' === path) {
    const body = normalizeBody<{ email: string; code: string }>(config);

    return ok(confirmRegistrationWithMock(body.email, body.code));
  }

  if ('post' === method && '/api/v1/auth/logout' === path) {
    logoutWithMock(normalizeToken(config));

    return ok([]);
  }

  const userId = ensureAuthorized(config);

  if ('post' === method && '/api/v1/share/file/upload/' === path) {
    if (!(config.data instanceof FormData)) {
      throw new MockHttpError(400, 'Ожидается multipart/form-data.');
    }

    const file = config.data.get('file');

    if (!(file instanceof File)) {
      throw new MockHttpError(400, 'Файл не передан.');
    }

    const uploadedFile: MockUploadedFile = {
      id: mockUploadedFileId++,
      name: file.name,
      size: file.size,
      type: file.type,
      src: await blobToDataUrl(file),
      contentType: resolveUploadedFileContentType(file.type)
    };

    mockUploadedFiles.set(uploadedFile.id, uploadedFile);

    return ok({
      id: uploadedFile.id,
      name: uploadedFile.name,
      size: uploadedFile.size,
      type: uploadedFile.type,
      src: uploadedFile.src
    });
  }

  if ('get' === method && '/api/v1/identity/connection/status' === path) {
    return ok(getApiStatusWithMock());
  }

  if ('post' === method && '/api/v1/identity/connection' === path) {
    const body = normalizeBody<{ apiKey: string; secretKey: string; mode: 'testnet' | 'mainnet' }>(config);

    return ok(connectApiWithMock(userId, body.apiKey, body.mode));
  }

  if ('delete' === method && '/api/v1/identity/connection' === path) {
    disconnectApiWithMock();

    return ok([]);
  }

  if ('post' === method && '/api/v1/identity/connection/verify' === path) {
    return ok(verifyApiWithMock());
  }

  if ('get' === method && '/api/v1/wallet/balances' === path) {
    return ok(getBalancesWithMock());
  }

  if ('post' === method && '/api/v1/wallet/balances/sync' === path) {
    return ok(syncBalancesWithMock());
  }

  if ('get' === method && '/api/v1/wallet/transactions' === path) {
    return ok(getTransactionsWithMock(config.params));
  }

  if ('get' === method && '/api/v1/wallet/transactions/export' === path) {
    return ok(getTransactionsWithMock(config.params));
  }

  if ('get' === method && '/api/v1/wallet/reports/cash-flow' === path) {
    return ok(getCashFlowReportWithMock(config.params));
  }

  if ('get' === method && '/api/v1/exchange/currencies' === path) {
    return ok({ items: getCurrenciesWithMock() });
  }

  if ('get' === method && '/api/v1/exchange/currency-pairs' === path) {
    return ok({ items: getCurrencyPairsWithMock() });
  }

  if ('get' === method && '/api/v1/exchange/payment-methods' === path) {
    return ok({ items: getPaymentMethodsWithMock() });
  }

  if ('get' === method && '/api/v1/exchange/orderbook' === path) {
    return ok(getOrderBookWithMock());
  }

  if ('get' === method && '/api/v1/exchange/advertisements' === path) {
    const status = 'string' === typeof config.params?.status ? config.params.status : undefined;

    return ok({ items: getAdvertisementsWithMock(status) });
  }

  if ('post' === method && '/api/v1/exchange/advertisements' === path) {
    return ok(createAdvertisementWithMock(normalizeBody(config)));
  }

  if ('patch' === method && /^\/api\/v1\/exchange\/advertisements\/\d+$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/advertisements\/(\d+)$/);
    const body = normalizeBody<{ status: 'active' | 'paused' }>(config);

    return ok(toggleAdvertisementWithMock(id, body.status));
  }

  if ('delete' === method && /^\/api\/v1\/exchange\/advertisements\/\d+$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/advertisements\/(\d+)$/);
    deleteAdvertisementWithMock(id);

    return ok([]);
  }

  if ('get' === method && '/api/v1/exchange/trades' === path) {
    const status = 'string' === typeof config.params?.status ? config.params.status : undefined;

    return ok({ items: getTradesWithMock(status) });
  }

  if ('get' === method && /^\/api\/v1\/exchange\/trades\/\d+$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)$/);

    return ok(getTradeDetailWithMock(id));
  }

  if ('post' === method && /^\/api\/v1\/exchange\/trades\/\d+\/pay$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)\/pay$/);

    return ok(confirmPaymentWithMock(id));
  }

  if ('post' === method && /^\/api\/v1\/exchange\/trades\/\d+\/release$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)\/release$/);

    return ok(releaseAssetsWithMock(id));
  }

  if ('get' === method && /^\/api\/v1\/exchange\/trades\/\d+\/counterparty$/.test(path)) {
    const tradeId = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)\/counterparty$/);

    return ok(getCounterpartyInfoWithMock(tradeId));
  }

  if ('get' === method && /^\/api\/v1\/exchange\/trades\/\d+\/chat$/.test(path)) {
    const tradeId = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)\/chat$/);

    return ok({ messages: getTradeMessagesWithMock(tradeId) });
  }

  if ('post' === method && /^\/api\/v1\/exchange\/trades\/\d+\/chat$/.test(path)) {
    const tradeId = getRouteId(path, /^\/api\/v1\/exchange\/trades\/(\d+)\/chat$/);

    return ok(sendTradeMessageWithMock(tradeId, normalizeBody(config)));
  }

  if ('post' === method && /^\/api\/v1\/exchange\/trades\/\d+\/chat\/upload$/.test(path)) {
    const body = normalizeBody<{ fileId: number }>(config);
    const uploadedFile = mockUploadedFiles.get(body.fileId);

    if (undefined === uploadedFile) {
      throw new MockHttpError(404, 'Файл не найден.');
    }

    return ok({
      fileName: uploadedFile.name,
      fileUrl: uploadedFile.src,
      contentType: uploadedFile.contentType,
      providerType: null
    });
  }

  if ('get' === method && '/api/v1/exchange/chat-scripts' === path) {
    return ok({ items: getChatScriptsWithMock() });
  }

  if ('post' === method && '/api/v1/exchange/chat-scripts' === path) {
    return ok(createChatScriptWithMock(normalizeBody(config)));
  }

  if ('patch' === method && /^\/api\/v1\/exchange\/chat-scripts\/\d+$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/chat-scripts\/(\d+)$/);

    return ok(updateChatScriptWithMock(id, normalizeBody(config)));
  }

  if ('delete' === method && /^\/api\/v1\/exchange\/chat-scripts\/\d+$/.test(path)) {
    const id = getRouteId(path, /^\/api\/v1\/exchange\/chat-scripts\/(\d+)$/);
    deleteChatScriptWithMock(id);

    return ok([]);
  }

  throw new MockHttpError(404, `Mock для маршрута ${method.toUpperCase()} ${path} не реализован.`);
}

function buildResponse(config: AxiosRequestConfig, data: MockEnvelope<unknown>, status = 200): AxiosResponse {
  return {
    data,
    status,
    statusText: 200 === status ? 'OK' : 'ERROR',
    headers: {},
    config
  } as AxiosResponse;
}

export const mockApiAdapter: AxiosAdapter = async (config) => {
  await sleep(mockNetworkDelayMs);

  try {
    const data = await handleMockRequest(config);

    return buildResponse(config, data);
  } catch (error) {
    if (error instanceof MockHttpError) {
      return Promise.reject({
        message: error.message,
        response: buildResponse(
          config,
          {
            data: null,
            error: {
              message: error.message
            }
          },
          error.status
        )
      });
    }

    const fallbackMessage = error instanceof Error ? error.message : 'Неизвестная ошибка mock API.';

    return Promise.reject({
      message: fallbackMessage,
      response: buildResponse(
        config,
        {
          data: null,
          error: {
            message: fallbackMessage
          }
        },
        500
      )
    });
  }
};
