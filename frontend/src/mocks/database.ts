import type { AuthUser, LoginRequest, LoginResponse, RegisterRequest, RequestRegistrationCodeResponse } from '@/api/auth';
import type { ChatContentType, ChatMessage, ChatScript, ChatScriptStep, Advertisement, OrderBookResponseDto, Trade } from '@/api/exchange';
import type { ApiConnectionMode, ApiConnectionState, ApiConnectionStatus } from '@/api/identity';
import type { Balance, BalanceListResponse, CashFlowFilters, CashFlowReport, Transaction, TransactionFilters } from '@/api/wallet';

const STORAGE_KEY = 'rebit:p2p:mock-state:v1';
const DEFAULT_USER_EMAIL = 'owner@rebit.test';
const DEFAULT_USER_PASSWORD = 'secret123';
const MOCK_TOKEN_TTL_MINUTES = 60 * 24;

type DictionaryCurrency = {
  id: number;
  code: string;
  name: string;
  type: 'crypto' | 'fiat';
  decimals: number;
  sort: number;
};

type DictionaryCurrencyPair = {
  id: number;
  code: string;
  tokenCurrencyId: number;
  fiatCurrencyId: number;
  tokenCode: string;
  fiatCode: string;
  isDefault: boolean;
  sort: number;
};

type DictionaryPaymentMethod = {
  id: number;
  code: string;
  name: string;
  sort: number;
};

type MockUser = AuthUser & {
  password: string;
};

export interface MockChatScriptStepRecord extends ChatScriptStep {
  id: number;
  contentType: ChatContentType;
  fileName: string | null;
  fileUrl: string | null;
}

export interface MockChatScriptRecord extends ChatScript {
  steps: MockChatScriptStepRecord[];
}

export interface MockTradeRecord extends Trade {
  isNew: boolean;
}

type MockChatMessagePayload = {
  senderType: ChatMessage['senderType'];
  message: string;
  contentType: ChatContentType;
  fileName: string | null;
  fileUrl: string | null;
};

type MockRegistration = RequestRegistrationCodeResponse &
  RegisterRequest & {
    code: string;
  };

export interface MockState {
  version: number;
  users: MockUser[];
  authTokens: Record<string, number>;
  registration: MockRegistration | null;
  connectionStatus: ApiConnectionStatus;
  balances: Balance[];
  transactions: Transaction[];
  chatScripts: MockChatScriptRecord[];
  advertisements: Advertisement[];
  trades: MockTradeRecord[];
  tradeMessages: Record<number, ChatMessage[]>;
  currencies: DictionaryCurrency[];
  currencyPairs: DictionaryCurrencyPair[];
  paymentMethods: DictionaryPaymentMethod[];
  orderBook: OrderBookResponseDto;
  nextIds: {
    user: number;
    connection: number;
    chatScript: number;
    chatScriptStep: number;
    advertisement: number;
    trade: number;
    message: number;
    transaction: number;
  };
  automation: {
    lastTradeGenerationAt: string | null;
    counterpartyIndex: number;
  };
}

const dictionaryCurrencies: DictionaryCurrency[] = [
  { id: 1, code: 'USDT', name: 'Tether USD', type: 'crypto', decimals: 2, sort: 10 },
  { id: 2, code: 'BTC', name: 'Bitcoin', type: 'crypto', decimals: 8, sort: 20 },
  { id: 3, code: 'RUB', name: 'Российский рубль', type: 'fiat', decimals: 2, sort: 100 }
];

const dictionaryCurrencyPairs: DictionaryCurrencyPair[] = [
  {
    id: 1,
    code: 'USDT_RUB',
    tokenCurrencyId: 1,
    fiatCurrencyId: 3,
    tokenCode: 'USDT',
    fiatCode: 'RUB',
    isDefault: true,
    sort: 10
  },
  {
    id: 2,
    code: 'BTC_RUB',
    tokenCurrencyId: 2,
    fiatCurrencyId: 3,
    tokenCode: 'BTC',
    fiatCode: 'RUB',
    isDefault: false,
    sort: 20
  }
];

const dictionaryPaymentMethods: DictionaryPaymentMethod[] = [
  { id: 19, code: 'SBP', name: 'СБП', sort: 10 },
  { id: 20, code: 'TINKOFF', name: 'T-Банк', sort: 20 },
  { id: 21, code: 'SBERBANK', name: 'Сбербанк', sort: 30 },
  { id: 22, code: 'BANK_TRANSFER', name: 'Банковский перевод', sort: 40 },
  { id: 23, code: 'BALANCE', name: 'Баланс Bybit', sort: 50 }
];

const counterpartyNames = ['Иван', 'Мария', 'Alex', 'Сергей', 'Olga'];
const MOCK_CHAT_SYNC_DELAY_MS = 4000;
const MOCK_BYBIT_ATTACHMENT_FILE_NAME = 'bybit-counterparty-check.svg';

let mockState: MockState | null = null;

function clone<TValue>(value: TValue): TValue {
  if ('function' === typeof globalThis.structuredClone) {
    return globalThis.structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value)) as TValue;
}

function nowIso(): string {
  return new Date().toISOString();
}

function shiftIso(minutes: number): string {
  return new Date(Date.now() + minutes * 60_000).toISOString();
}

function shiftIsoSeconds(seconds: number): string {
  return new Date(Date.now() + seconds * 1000).toISOString();
}

function createToken(userId: number): string {
  return `mock-token-${userId}-${Date.now()}`;
}

function createEmptyConnectionStatus(): ApiConnectionStatus {
  return {
    connected: false,
    mode: null,
    modeLabel: null,
    status: null,
    statusLabel: null,
    id: null,
    userId: null,
    maskedApiKey: null,
    createdAt: null,
    verifiedAt: null
  };
}

function formatAmount(value: number, decimals: number): string {
  const normalized = value.toFixed(decimals).replace(/\.?0+$/, '');

  return '' === normalized ? '0' : normalized;
}

function parseNumericValue(value: number | string): number {
  return 'number' === typeof value ? value : Number.parseFloat(value);
}

function getCurrencyByCode(state: MockState, code: string): DictionaryCurrency | undefined {
  return state.currencies.find((currency) => code === currency.code);
}

function getCurrencyById(state: MockState, currencyId: number): DictionaryCurrency | undefined {
  return state.currencies.find((currency) => currencyId === currency.id);
}

function getPairById(state: MockState, pairId: number): DictionaryCurrencyPair | undefined {
  return state.currencyPairs.find((pair) => pairId === pair.id);
}

function findUserByEmail(state: MockState, email: string): MockUser | undefined {
  return state.users.find((user) => email.toLowerCase() === user.email.toLowerCase());
}

function getOrCreateUser(state: MockState, email: string, password: string): MockUser {
  const existingUser = findUserByEmail(state, email);

  if (undefined !== existingUser) {
    existingUser.password = password;

    return existingUser;
  }

  const user: MockUser = {
    id: state.nextIds.user++,
    email,
    password,
    name: email.split('@')[0] || 'Пользователь'
  };

  state.users.push(user);

  return user;
}

function createOrderBook(): OrderBookResponseDto {
  return {
    buy: [
      {
        id: 101,
        bybitOrderId: 'ob-buy-1',
        side: 'buy',
        price: 93.1,
        amount: 1500,
        minLimit: 1000,
        maxLimit: 50000,
        username: 'market_buyer_01',
        completedTrades: 352,
        completionRate: 99.2,
        paymentMethods: ['SBP', 'TINKOFF'],
        paymentTimeLimit: 15
      },
      {
        id: 102,
        bybitOrderId: 'ob-buy-2',
        side: 'buy',
        price: 92.85,
        amount: 900,
        minLimit: 500,
        maxLimit: 35000,
        username: 'otc_fast',
        completedTrades: 128,
        completionRate: 98.5,
        paymentMethods: ['SBERBANK'],
        paymentTimeLimit: 15
      }
    ],
    sell: [
      {
        id: 201,
        bybitOrderId: 'ob-sell-1',
        side: 'sell',
        price: 93.55,
        amount: 1200,
        minLimit: 1000,
        maxLimit: 60000,
        username: 'market_seller_01',
        completedTrades: 286,
        completionRate: 97.9,
        paymentMethods: ['SBP', 'BANK_TRANSFER'],
        paymentTimeLimit: 15
      },
      {
        id: 202,
        bybitOrderId: 'ob-sell-2',
        side: 'sell',
        price: 93.9,
        amount: 600,
        minLimit: 300,
        maxLimit: 20000,
        username: 'trusted_dealer',
        completedTrades: 511,
        completionRate: 99.6,
        paymentMethods: ['TINKOFF'],
        paymentTimeLimit: 15
      }
    ]
  };
}

function createInitialState(): MockState {
  return {
    version: 1,
    users: [
      {
        id: 1,
        email: DEFAULT_USER_EMAIL,
        password: DEFAULT_USER_PASSWORD,
        name: 'Владелец аккаунта'
      }
    ],
    authTokens: {},
    registration: null,
    connectionStatus: createEmptyConnectionStatus(),
    balances: [],
    transactions: [],
    chatScripts: [],
    advertisements: [],
    trades: [],
    tradeMessages: {},
    currencies: clone(dictionaryCurrencies),
    currencyPairs: clone(dictionaryCurrencyPairs),
    paymentMethods: clone(dictionaryPaymentMethods),
    orderBook: createOrderBook(),
    nextIds: {
      user: 2,
      connection: 1,
      chatScript: 1,
      chatScriptStep: 1,
      advertisement: 1,
      trade: 1,
      message: 1,
      transaction: 1
    },
    automation: {
      lastTradeGenerationAt: null,
      counterpartyIndex: 0
    }
  };
}

function saveState(state: MockState): void {
  if ('undefined' === typeof window) {
    return;
  }

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function loadState(): MockState {
  if ('undefined' === typeof window) {
    return createInitialState();
  }

  const rawState = window.localStorage.getItem(STORAGE_KEY);

  if (null === rawState) {
    const initialState = createInitialState();
    saveState(initialState);

    return initialState;
  }

  try {
    const parsedState = JSON.parse(rawState) as Partial<MockState>;

    if (1 !== parsedState.version) {
      const initialState = createInitialState();
      saveState(initialState);

      return initialState;
    }

    const baseState = createInitialState();
    const restoredState: MockState = {
      ...baseState,
      ...parsedState,
      nextIds: {
        ...baseState.nextIds,
        ...(parsedState.nextIds ?? {})
      },
      automation: {
        ...baseState.automation,
        ...(parsedState.automation ?? {})
      },
      connectionStatus: {
        ...baseState.connectionStatus,
        ...(parsedState.connectionStatus ?? {})
      },
      authTokens: parsedState.authTokens ?? {},
      users: parsedState.users ?? baseState.users,
      balances: parsedState.balances ?? [],
      transactions: parsedState.transactions ?? [],
      chatScripts: parsedState.chatScripts ?? [],
      advertisements: parsedState.advertisements ?? [],
      trades: (parsedState.trades ?? []).map((trade) => ({
        ...trade,
        isNew: trade.isNew
      })),
      tradeMessages: parsedState.tradeMessages ?? {},
      currencies: parsedState.currencies ?? baseState.currencies,
      currencyPairs: parsedState.currencyPairs ?? baseState.currencyPairs,
      paymentMethods: parsedState.paymentMethods ?? baseState.paymentMethods,
      orderBook: parsedState.orderBook ?? baseState.orderBook,
      registration: parsedState.registration ?? null
    };

    saveState(restoredState);

    return restoredState;
  } catch {
    const initialState = createInitialState();
    saveState(initialState);

    return initialState;
  }
}

export function getMockState(): MockState {
  if (null === mockState) {
    mockState = loadState();
  }

  return mockState;
}

export function getMockStateSnapshot(): MockState {
  return clone(getMockState());
}

export function resetMockState(): MockState {
  mockState = createInitialState();
  saveState(mockState);

  return clone(mockState);
}

export function commitMockState(): void {
  saveState(getMockState());
}

function createChatMessage(state: MockState, tradeId: number, payload: MockChatMessagePayload): ChatMessage {
  const message: ChatMessage = {
    id: state.nextIds.message++,
    tradeId,
    senderType: payload.senderType,
    message: payload.message,
    contentType: payload.contentType,
    fileName: payload.fileName ?? null,
    fileUrl: payload.fileUrl ?? null,
    createdAt: nowIso()
  };

  if (undefined === state.tradeMessages[tradeId]) {
    state.tradeMessages[tradeId] = [];
  }

  state.tradeMessages[tradeId]?.push(message);

  return message;
}

function createMockBybitAttachmentUrl(): string {
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="320" height="200" viewBox="0 0 320 200">
      <rect width="320" height="200" rx="16" fill="#f5f7fb" />
      <rect x="20" y="20" width="280" height="160" rx="12" fill="#ffffff" stroke="#c8d1e1" />
      <text x="36" y="70" font-family="Arial, sans-serif" font-size="22" fill="#1f2937">ByBit attachment</text>
      <text x="36" y="108" font-family="Arial, sans-serif" font-size="16" fill="#4b5563">Counterparty shared a payment proof</text>
      <circle cx="264" cy="72" r="18" fill="#10b981" />
      <path d="M255 72l7 7 14-16" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  `;

  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

function syncIncomingTradeMessagesWithMock(state: MockState, tradeId: number): boolean {
  const trade = state.trades.find((item) => tradeId === item.id);

  if (undefined === trade || 'cancelled' === trade.status || 'completed' === trade.status) {
    return false;
  }

  const messages = state.tradeMessages[tradeId] ?? [];

  if (0 === messages.length) {
    return false;
  }

  const lastMessage = messages[messages.length - 1];

  if (undefined === lastMessage) {
    return false;
  }

  const lastMessageAgeMs = Date.now() - new Date(lastMessage.createdAt).getTime();

  if (!Number.isFinite(lastMessageAgeMs) || MOCK_CHAT_SYNC_DELAY_MS > lastMessageAgeMs) {
    return false;
  }

  const hasCounterpartyIntro = messages.some(
    (message) => 'user' === message.senderType && 'Контрагент: реквизиты отправлены, ожидаю перевод.' === message.message
  );

  if ('pending_payment' === trade.status && !hasCounterpartyIntro) {
    createChatMessage(state, tradeId, {
      senderType: 'user',
      message: 'Контрагент: реквизиты отправлены, ожидаю перевод.',
      contentType: 'str',
      fileName: null,
      fileUrl: null
    });

    return true;
  }

  const hasCounterpartyAttachment = messages.some((message) => MOCK_BYBIT_ATTACHMENT_FILE_NAME === message.fileName);

  if ('payment_sent' === trade.status && !hasCounterpartyAttachment) {
    createChatMessage(state, tradeId, {
      senderType: 'user',
      message: 'Контрагент: прикладываю подтверждение из ByBit.',
      contentType: 'pic',
      fileName: MOCK_BYBIT_ATTACHMENT_FILE_NAME,
      fileUrl: createMockBybitAttachmentUrl()
    });

    return true;
  }

  return false;
}

function seedBalances(state: MockState): void {
  if (0 < state.balances.length) {
    return;
  }

  state.balances = [
    {
      id: 1,
      userId: state.connectionStatus.userId ?? 1,
      currencyId: 1,
      currency: 'USDT',
      available: '1250.5',
      locked: '0',
      total: '1250.5',
      syncedAt: nowIso()
    },
    {
      id: 2,
      userId: state.connectionStatus.userId ?? 1,
      currencyId: 2,
      currency: 'BTC',
      available: '0.15',
      locked: '0',
      total: '0.15',
      syncedAt: nowIso()
    },
    {
      id: 3,
      userId: state.connectionStatus.userId ?? 1,
      currencyId: 3,
      currency: 'RUB',
      available: '120000',
      locked: '0',
      total: '120000',
      syncedAt: nowIso()
    }
  ];
}

function addTransaction(state: MockState, payload: Omit<Transaction, 'id' | 'createdAt'> & { createdAt?: string }): Transaction {
  const transaction: Transaction = {
    id: String(state.nextIds.transaction++),
    type: payload.type,
    amount: payload.amount,
    currency: payload.currency,
    tradeId: payload.tradeId ?? null,
    createdAt: payload.createdAt ?? nowIso()
  };

  state.transactions.unshift(transaction);

  return transaction;
}

function seedTransactions(state: MockState): void {
  if (0 < state.transactions.length) {
    return;
  }

  addTransaction(state, {
    type: 'deposit',
    amount: '1500',
    currency: 'USDT',
    tradeId: null,
    createdAt: shiftIso(-60 * 24 * 7)
  });

  addTransaction(state, {
    type: 'trade_sell',
    amount: '250',
    currency: 'USDT',
    tradeId: 'history-1',
    createdAt: shiftIso(-60 * 24 * 2)
  });

  addTransaction(state, {
    type: 'fee',
    amount: '0.5',
    currency: 'USDT',
    tradeId: 'history-1',
    createdAt: shiftIso(-60 * 24 * 2)
  });
}

function seedHistoricalTrade(state: MockState): void {
  if (state.trades.some((trade) => null === trade.advertisementId && 'completed' === trade.status)) {
    return;
  }

  const tradeId = state.nextIds.trade++;
  const createdAt = shiftIso(-60 * 24 * 2);
  const completedAt = shiftIso(-60 * 24 * 2 + 12);
  const historicalTrade: MockTradeRecord = {
    id: tradeId,
    bybitOrderId: `history-${tradeId}`,
    bybitStatus: 30,
    side: 'sell',
    price: 92.4,
    quantity: 250,
    fiatAmount: 23100,
    fee: 0.5,
    status: 'completed',
    counterpartyName: 'history_buyer',
    currencyPairId: 1,
    advertisementId: null,
    paymentDeadline: shiftIso(-60 * 24 * 2 + 15),
    paidAt: shiftIso(-60 * 24 * 2 + 5),
    completedAt,
    cancelledAt: null,
    cancelReason: null,
    createdAt,
    updatedAt: completedAt,
    isNew: false
  };

  state.trades.push(historicalTrade);

  createChatMessage(state, tradeId, {
    senderType: 'system',
    message: 'Историческая сделка успешно завершена.',
    contentType: 'str',
    fileName: null,
    fileUrl: null
  });
}

function seedConnectionData(state: MockState): void {
  seedBalances(state);
  seedTransactions(state);
  seedHistoricalTrade(state);
}

function adjustBalance(state: MockState, currencyCode: string, availableDelta: number, lockedDelta: number): void {
  const currency = getCurrencyByCode(state, currencyCode);
  const balance = state.balances.find((item) => currencyCode === item.currency);

  if (undefined === currency || undefined === balance) {
    return;
  }

  const nextAvailable = parseNumericValue(balance.available) + availableDelta;
  const nextLocked = parseNumericValue(balance.locked) + lockedDelta;
  const normalizedAvailable = Math.max(0, nextAvailable);
  const normalizedLocked = Math.max(0, nextLocked);

  balance.available = formatAmount(normalizedAvailable, currency.decimals);
  balance.locked = formatAmount(normalizedLocked, currency.decimals);
  balance.total = formatAmount(normalizedAvailable + normalizedLocked, currency.decimals);
  balance.syncedAt = nowIso();
}

function syncBalancesTimestamp(state: MockState): void {
  const syncedAt = nowIso();

  state.balances = state.balances.map((balance) => ({
    ...balance,
    syncedAt
  }));
}

function getApproximateRubRate(state: MockState, currencyCode: string): number | null {
  const code = currencyCode.toUpperCase();

  if ('RUB' === code) {
    return 1;
  }

  // 1. Прямой P2P-курс из стакана USDT_RUB
  const usdtRubRate = getBestBuyPrice(state);

  if ('USDT' === code) {
    return usdtRubRate;
  }

  // 2. Кросс-курс: TOKEN→USDT (spot) × USDT→RUB (P2P)
  if (null !== usdtRubRate) {
    const spotPrices: Record<string, number> = {
      BTC: 84000,
      USDC: 1
    };
    const spotPrice = spotPrices[code] ?? null;

    if (null !== spotPrice) {
      return spotPrice * usdtRubRate;
    }
  }

  return null;
}

function getBestBuyPrice(state: MockState): number | null {
  let bestBuyPrice: number | null = null;

  for (const order of state.orderBook.buy) {
    const price = 'number' === typeof order.price ? order.price : Number.parseFloat(order.price);

    if (Number.isNaN(price) || 0 >= price) {
      continue;
    }

    if (null === bestBuyPrice || price > bestBuyPrice) {
      bestBuyPrice = price;
    }
  }

  return bestBuyPrice;
}

function buildBalancesResponse(state: MockState): BalanceListResponse {
  const balances = state.balances.map((balance) => {
    const rubRate = getApproximateRubRate(state, balance.currency);
    const total = parseNumericValue(balance.total);

    return {
      ...balance,
      rubRate,
      rubEquivalent: null === rubRate || Number.isNaN(total) ? null : total * rubRate
    } satisfies Balance;
  });

  return {
    balances: clone(balances),
    totalRubEquivalent:
      0 === balances.length || !balances.some((b) => null !== b.rubEquivalent)
        ? null
        : balances.reduce((sum, balance) => sum + (balance.rubEquivalent ?? 0), 0)
  };
}

function renderScriptMessage(step: MockChatScriptStepRecord, trade: MockTradeRecord): string {
  return step.message
    .replace(/{counterparty}/g, trade.counterpartyName)
    .replace(/{amount}/g, String(trade.quantity))
    .replace(/{currency}/g, 'USDT')
    .replace(/{fiat_amount}/g, String(trade.fiatAmount))
    .replace(/{fiat_currency}/g, 'RUB')
    .replace(/{trade_id}/g, String(trade.id));
}

function createTradeFromAdvertisement(state: MockState, advertisementId?: number): MockTradeRecord | null {
  const advertisement =
    undefined === advertisementId
      ? state.advertisements.find((item) => 'active' === item.status)
      : state.advertisements.find((item) => advertisementId === item.id);

  if (undefined === advertisement || 'active' !== advertisement.status) {
    return null;
  }

  const pair = getPairById(state, advertisement.currencyPairId);

  if (undefined === pair) {
    return null;
  }

  const counterpartyName = counterpartyNames[state.automation.counterpartyIndex % counterpartyNames.length] ?? 'Контрагент';
  state.automation.counterpartyIndex += 1;

  const price = Number.parseFloat(advertisement.price);
  const quantity = Number.parseFloat(advertisement.quantity);
  const minAmount = Number.parseFloat(advertisement.minAmount);
  const fiatAmount = Number.isFinite(price * quantity) ? Math.max(minAmount, Number((price * quantity).toFixed(2))) : minAmount;
  const tradeId = state.nextIds.trade++;
  const trade: MockTradeRecord = {
    id: tradeId,
    bybitOrderId: `mock-${tradeId}-${Date.now()}`,
    bybitStatus: 10,
    side: advertisement.side,
    price,
    quantity,
    fiatAmount,
    fee: Number((quantity * 0.001).toFixed(4)),
    status: 'pending_payment',
    counterpartyName,
    currencyPairId: advertisement.currencyPairId,
    advertisementId: advertisement.id,
    paymentDeadline: shiftIso(15),
    paidAt: null,
    completedAt: null,
    cancelledAt: null,
    cancelReason: null,
    createdAt: nowIso(),
    updatedAt: nowIso(),
    isNew: true
  };

  state.trades.unshift(trade);
  state.automation.lastTradeGenerationAt = trade.createdAt;

  createChatMessage(state, tradeId, {
    senderType: 'system',
    message: `Появилась новая сделка по объявлению #${advertisement.id}. Ожидается действие пользователя.`,
    contentType: 'str',
    fileName: null,
    fileUrl: null
  });

  const chatScript =
    null === advertisement.chatScriptId
      ? null
      : state.chatScripts.find((script) => advertisement.chatScriptId === script.id && script.isActive);
  const firstStep = chatScript?.steps.slice().sort((first, second) => first.sort - second.sort)[0] ?? null;

  if (null !== firstStep) {
    createChatMessage(state, tradeId, {
      senderType: 'script',
      message: renderScriptMessage(firstStep, trade),
      contentType: firstStep.contentType,
      fileName: firstStep.fileName,
      fileUrl: firstStep.fileUrl
    });
  }

  return trade;
}

export function ensureAutomatedTrade(): MockTradeRecord | null {
  const state = getMockState();
  const activeAdvertisement = state.advertisements.find((advertisement) => {
    if ('active' !== advertisement.status) {
      return false;
    }

    return !state.trades.some(
      (trade) => advertisement.id === trade.advertisementId && 'cancelled' !== trade.status && 'completed' !== trade.status
    );
  });

  if (undefined === activeAdvertisement) {
    return null;
  }

  const createdAt = new Date(activeAdvertisement.createdAt).getTime();

  if (Date.now() - createdAt < 5000) {
    return null;
  }

  if (null !== state.automation.lastTradeGenerationAt) {
    const lastGenerationAt = new Date(state.automation.lastTradeGenerationAt).getTime();

    if (Date.now() - lastGenerationAt < 10_000) {
      return null;
    }
  }

  const trade = createTradeFromAdvertisement(state, activeAdvertisement.id);
  commitMockState();

  return null === trade ? null : clone(trade);
}

export function generateTradeForActiveAdvertisement(advertisementId?: number): MockTradeRecord | null {
  const state = getMockState();
  const trade = createTradeFromAdvertisement(state, advertisementId);

  if (null !== trade) {
    commitMockState();

    return clone(trade);
  }

  return null;
}

export function loginWithMock(request: LoginRequest): LoginResponse {
  const state = getMockState();
  const normalizedEmail = request.email.trim().toLowerCase();
  const user = findUserByEmail(state, normalizedEmail);

  if (undefined === user) {
    throw new Error('Пользователь не найден. Используйте owner@rebit.test / secret123 или пройдите mock-регистрацию.');
  }

  if (request.password !== user.password) {
    throw new Error('Неверный пароль. Для mock-режима используйте secret123.');
  }

  const token = createToken(user.id);
  state.authTokens[token] = user.id;
  commitMockState();

  return {
    token,
    expiresAt: shiftIso(MOCK_TOKEN_TTL_MINUTES),
    user: {
      id: user.id,
      email: user.email,
      name: user.name
    }
  };
}

export function requestRegistrationCodeWithMock(request: RegisterRequest): RequestRegistrationCodeResponse {
  const state = getMockState();
  state.registration = {
    ...request,
    email: request.email.trim().toLowerCase(),
    code: '123456',
    codeExpiresAt: shiftIso(15),
    resendAvailableAt: shiftIsoSeconds(60)
  };
  commitMockState();

  return {
    email: state.registration.email,
    codeExpiresAt: state.registration.codeExpiresAt,
    resendAvailableAt: state.registration.resendAvailableAt
  };
}

export function confirmRegistrationWithMock(email: string, code: string): LoginResponse {
  const state = getMockState();

  if (null === state.registration || email.trim().toLowerCase() !== state.registration.email) {
    throw new Error('Сначала запросите код регистрации.');
  }

  if (code !== state.registration.code) {
    throw new Error('Неверный код подтверждения. Для mock-режима используйте 123456.');
  }

  const user = getOrCreateUser(state, state.registration.email, state.registration.password);
  const token = createToken(user.id);
  state.authTokens[token] = user.id;
  state.registration = null;
  commitMockState();

  return {
    token,
    expiresAt: shiftIso(MOCK_TOKEN_TTL_MINUTES),
    user: {
      id: user.id,
      email: user.email,
      name: user.name
    }
  };
}

export function logoutWithMock(token: string | null): void {
  if (null === token) {
    return;
  }

  const state = getMockState();
  delete state.authTokens[token];
  commitMockState();
}

export function resolveUserIdByToken(token: string | null): number | null {
  if (null === token) {
    return null;
  }

  const state = getMockState();

  return state.authTokens[token] ?? null;
}

function buildStatusLabel(status: ApiConnectionState | null): string | null {
  if (null === status) {
    return null;
  }

  return (
    {
      active: 'Активен',
      invalid: 'Недействителен',
      revoked: 'Отозван',
      pending_verification: 'Ожидает проверки'
    }[status] ?? null
  );
}

function buildModeLabel(mode: ApiConnectionMode | null): string | null {
  if (null === mode) {
    return null;
  }

  return 'mainnet' === mode ? 'Mainnet' : 'Testnet';
}

export function connectApiWithMock(userId: number, apiKey: string, mode: ApiConnectionMode): ApiConnectionStatus {
  const state = getMockState();
  state.connectionStatus = {
    connected: true,
    mode,
    modeLabel: buildModeLabel(mode),
    status: 'active',
    statusLabel: buildStatusLabel('active'),
    id: state.nextIds.connection++,
    userId,
    maskedApiKey: `${'*'.repeat(Math.max(0, apiKey.length - 4))}${apiKey.slice(-4)}`,
    createdAt: nowIso(),
    verifiedAt: nowIso()
  };
  seedConnectionData(state);
  commitMockState();

  return clone(state.connectionStatus);
}

export function disconnectApiWithMock(): void {
  const state = getMockState();
  state.connectionStatus = createEmptyConnectionStatus();
  commitMockState();
}

export function verifyApiWithMock(): ApiConnectionStatus {
  const state = getMockState();

  if (!state.connectionStatus.connected) {
    throw new Error('Bybit API не подключён.');
  }

  state.connectionStatus.verifiedAt = nowIso();
  state.connectionStatus.status = 'active';
  state.connectionStatus.statusLabel = buildStatusLabel('active');
  commitMockState();

  return clone(state.connectionStatus);
}

export function getApiStatusWithMock(): ApiConnectionStatus {
  return clone(getMockState().connectionStatus);
}

export function getBalancesWithMock(): BalanceListResponse {
  return buildBalancesResponse(getMockState());
}

export function syncBalancesWithMock(): BalanceListResponse {
  const state = getMockState();
  syncBalancesTimestamp(state);
  commitMockState();

  return buildBalancesResponse(state);
}

export function getTransactionsWithMock(filters?: TransactionFilters): { transactions: Transaction[]; total: number } {
  const state = getMockState();
  let transactions = [...state.transactions];

  if (undefined !== filters?.type) {
    transactions = transactions.filter((transaction) => filters.type === transaction.type);
  }

  if (undefined !== filters?.dateFrom) {
    transactions = transactions.filter((transaction) => transaction.createdAt.slice(0, 10) >= filters.dateFrom!);
  }

  if (undefined !== filters?.dateTo) {
    transactions = transactions.filter((transaction) => transaction.createdAt.slice(0, 10) <= filters.dateTo!);
  }

  const total = transactions.length;
  const offset = filters?.offset ?? 0;
  const limit = filters?.limit ?? total;

  return {
    transactions: clone(transactions.slice(offset, offset + limit)),
    total
  };
}

export function getCashFlowReportWithMock(filters?: CashFlowFilters): CashFlowReport {
  const state = getMockState();
  const dateFrom = filters?.dateFrom ?? null;
  const dateTo = filters?.dateTo ?? null;
  const allowedCurrencyId = filters?.currencyId ?? null;

  const grouped = new Map<number, CashFlowReport['items'][number]>();

  state.transactions.forEach((transaction) => {
    const currency = getCurrencyByCode(state, transaction.currency);

    if (undefined === currency) {
      return;
    }

    if (null !== allowedCurrencyId && currency.id !== allowedCurrencyId) {
      return;
    }

    const txDate = transaction.createdAt.slice(0, 10);

    if (null !== dateFrom && txDate < dateFrom) {
      return;
    }

    if (null !== dateTo && txDate > dateTo) {
      return;
    }

    const existingItem = grouped.get(currency.id) ?? {
      currencyId: currency.id,
      currency: currency.code,
      openingBalance: 0,
      incoming: 0,
      outgoing: 0,
      closingBalance: 0
    };

    const amount = Number.parseFloat(transaction.amount);

    if (['deposit', 'trade_sell', 'unlock'].includes(transaction.type)) {
      existingItem.incoming += amount;
    }

    if (['withdrawal', 'trade_buy', 'lock', 'fee'].includes(transaction.type)) {
      existingItem.outgoing += amount;
    }

    grouped.set(currency.id, existingItem);
  });

  const items = Array.from(grouped.values()).map((item) => {
    const balance = state.balances.find((entry) => item.currency === entry.currency);
    const closingBalance = undefined === balance ? item.incoming - item.outgoing : parseNumericValue(balance.total);
    const openingBalance = Math.max(0, closingBalance - item.incoming + item.outgoing);

    return {
      ...item,
      openingBalance,
      closingBalance
    };
  });

  const totals =
    0 === items.length
      ? null
      : items.reduce(
          (carry, item) => ({
            totalIncoming: carry.totalIncoming + item.incoming,
            totalOutgoing: carry.totalOutgoing + item.outgoing,
            totalOpeningBalance: carry.totalOpeningBalance + item.openingBalance,
            totalClosingBalance: carry.totalClosingBalance + item.closingBalance
          }),
          {
            totalIncoming: 0,
            totalOutgoing: 0,
            totalOpeningBalance: 0,
            totalClosingBalance: 0
          }
        );

  return {
    items: clone(items),
    totals
  };
}

export function getCurrenciesWithMock(): DictionaryCurrency[] {
  return clone(getMockState().currencies);
}

export function getCurrencyPairsWithMock(): DictionaryCurrencyPair[] {
  return clone(getMockState().currencyPairs);
}

export function getPaymentMethodsWithMock(): DictionaryPaymentMethod[] {
  return clone(getMockState().paymentMethods);
}

export function getOrderBookWithMock(): OrderBookResponseDto {
  return clone(getMockState().orderBook);
}

export function getAdvertisementsWithMock(status?: Advertisement['status']): Advertisement[] {
  const state = getMockState();
  const advertisements =
    undefined === status ? state.advertisements : state.advertisements.filter((advertisement) => status === advertisement.status);

  return clone(advertisements);
}

export function createAdvertisementWithMock(payload: Omit<Advertisement, 'id' | 'status' | 'createdAt' | 'updatedAt'>): Advertisement {
  const state = getMockState();
  const createdAt = nowIso();
  const advertisement: Advertisement = {
    id: state.nextIds.advertisement++,
    currencyPairId: payload.currencyPairId,
    side: payload.side,
    priceType: payload.priceType,
    price: payload.price,
    premium: payload.premium,
    quantity: payload.quantity,
    minAmount: payload.minAmount,
    maxAmount: payload.maxAmount,
    paymentMethodIds: payload.paymentMethodIds,
    paymentPeriod: payload.paymentPeriod,
    conditions: payload.conditions,
    chatScriptId: payload.chatScriptId,
    status: 'active',
    createdAt,
    updatedAt: createdAt
  };

  const pair = getPairById(state, advertisement.currencyPairId);
  const quantity = Number.parseFloat(advertisement.quantity);

  if (undefined !== pair && 'sell' === advertisement.side && 0 < quantity) {
    const tokenCurrency = getCurrencyById(state, pair.tokenCurrencyId);
    const balance = undefined === tokenCurrency ? undefined : state.balances.find((item) => tokenCurrency.code === item.currency);

    if (undefined !== tokenCurrency && undefined !== balance) {
      if (parseNumericValue(balance.available) < quantity) {
        throw new Error(`Недостаточно ${tokenCurrency.code} для создания объявления.`);
      }

      adjustBalance(state, tokenCurrency.code, -quantity, quantity);
      addTransaction(state, {
        type: 'lock',
        amount: formatAmount(quantity, tokenCurrency.decimals),
        currency: tokenCurrency.code,
        tradeId: null
      });
    }
  }

  state.advertisements.unshift(advertisement);
  commitMockState();

  return clone(advertisement);
}

export function deleteAdvertisementWithMock(id: number): void {
  const state = getMockState();
  const advertisement = state.advertisements.find((item) => id === item.id);

  if (undefined === advertisement) {
    throw new Error('Объявление не найдено.');
  }

  if ('cancelled' !== advertisement.status && 'completed' !== advertisement.status) {
    toggleAdvertisementWithMock(id, 'paused');
  }

  advertisement.status = 'cancelled';
  advertisement.updatedAt = nowIso();
  commitMockState();
}

export function toggleAdvertisementWithMock(id: number, status: 'active' | 'paused'): Advertisement {
  const state = getMockState();
  const advertisement = state.advertisements.find((item) => id === item.id);

  if (undefined === advertisement) {
    throw new Error('Объявление не найдено.');
  }

  if (advertisement.status === status) {
    return clone(advertisement);
  }

  const pair = getPairById(state, advertisement.currencyPairId);
  const quantity = Number.parseFloat(advertisement.quantity);

  if (undefined !== pair && 'sell' === advertisement.side && 0 < quantity) {
    const tokenCurrency = getCurrencyById(state, pair.tokenCurrencyId);

    if (undefined !== tokenCurrency) {
      if ('active' === status) {
        const balance = state.balances.find((item) => tokenCurrency.code === item.currency);

        if (undefined !== balance && parseNumericValue(balance.available) < quantity) {
          throw new Error(`Недостаточно ${tokenCurrency.code} для активации объявления.`);
        }

        adjustBalance(state, tokenCurrency.code, -quantity, quantity);
        addTransaction(state, {
          type: 'lock',
          amount: formatAmount(quantity, tokenCurrency.decimals),
          currency: tokenCurrency.code,
          tradeId: null
        });
      }

      if ('paused' === status && 'active' === advertisement.status) {
        adjustBalance(state, tokenCurrency.code, quantity, -quantity);
        addTransaction(state, {
          type: 'unlock',
          amount: formatAmount(quantity, tokenCurrency.decimals),
          currency: tokenCurrency.code,
          tradeId: null
        });
      }
    }
  }

  advertisement.status = status;
  advertisement.updatedAt = nowIso();
  commitMockState();

  return clone(advertisement);
}

export function getTradesWithMock(status?: Trade['status']): MockTradeRecord[] {
  ensureAutomatedTrade();
  const state = getMockState();
  const trades = undefined === status ? state.trades : state.trades.filter((trade) => status === trade.status);

  return clone(trades);
}

export function getTradeDetailWithMock(id: number): MockTradeRecord {
  ensureAutomatedTrade();
  const state = getMockState();
  const trade = state.trades.find((item) => id === item.id);

  if (undefined === trade) {
    throw new Error('Сделка не найдена.');
  }

  trade.isNew = false;
  trade.updatedAt = nowIso();
  commitMockState();

  return clone(trade);
}

export function confirmPaymentWithMock(id: number): MockTradeRecord {
  const state = getMockState();
  const trade = state.trades.find((item) => id === item.id);

  if (undefined === trade) {
    throw new Error('Сделка не найдена.');
  }

  trade.status = 'payment_sent';
  trade.paidAt = nowIso();
  trade.updatedAt = nowIso();
  trade.isNew = false;

  createChatMessage(state, trade.id, {
    senderType: 'system',
    message: 'Покупатель отметил перевод как отправленный. Ожидается отпуск средств продавцом.',
    contentType: 'str',
    fileName: null,
    fileUrl: null
  });
  commitMockState();

  return clone(trade);
}

export function releaseAssetsWithMock(id: number): MockTradeRecord {
  const state = getMockState();
  const trade = state.trades.find((item) => id === item.id);

  if (undefined === trade) {
    throw new Error('Сделка не найдена.');
  }

  trade.status = 'completed';
  trade.completedAt = nowIso();
  trade.updatedAt = nowIso();
  trade.isNew = false;

  const pair = getPairById(state, trade.currencyPairId);

  if (undefined !== pair) {
    const tokenCurrency = getCurrencyById(state, pair.tokenCurrencyId);

    if (undefined !== tokenCurrency && 'sell' === trade.side) {
      adjustBalance(state, tokenCurrency.code, 0, -trade.quantity);
      addTransaction(state, {
        type: 'trade_sell',
        amount: formatAmount(trade.quantity, tokenCurrency.decimals),
        currency: tokenCurrency.code,
        tradeId: String(trade.id)
      });
      addTransaction(state, {
        type: 'fee',
        amount: formatAmount(trade.fee, tokenCurrency.decimals),
        currency: tokenCurrency.code,
        tradeId: String(trade.id)
      });
    }
  }

  createChatMessage(state, trade.id, {
    senderType: 'system',
    message: 'Средства отпущены. Сделка завершена успешно.',
    contentType: 'str',
    fileName: null,
    fileUrl: null
  });
  commitMockState();

  return clone(trade);
}

export function getTradeMessagesWithMock(tradeId: number): ChatMessage[] {
  const state = getMockState();

  if (syncIncomingTradeMessagesWithMock(state, tradeId)) {
    commitMockState();
  }

  return clone(state.tradeMessages[tradeId] ?? []);
}

export function getCounterpartyInfoWithMock(tradeId: number): Record<string, unknown> {
  const state = getMockState();
  const trade = state.trades.find((item) => tradeId === item.id);

  if (undefined === trade) {
    throw new Error('Сделка не найдена.');
  }

  const nameIndex = counterpartyNames.indexOf(trade.counterpartyName);
  const seed = -1 === nameIndex ? 0 : nameIndex;

  return {
    nickName: trade.counterpartyName,
    realName: `${trade.counterpartyName} Иванов`,
    realNameEn: `${trade.counterpartyName} Ivanov`,
    isOnline: 0 === seed % 2,
    kycLevel: 1 + (seed % 3),
    kycCountryCode: ['RUS', 'KAZ', 'UZB', 'BLR', 'TUR'][seed % 5] ?? 'RUS',
    email: `${trade.counterpartyName.toLowerCase()}***@mail.com`,
    mobile: '+7 *** *** ** ' + String(10 + seed).slice(-2),
    totalFinishCount: 120 + seed * 37,
    totalFinishBuyCount: 80 + seed * 20,
    totalFinishSellCount: 40 + seed * 17,
    recentFinishCount: 15 + seed * 3,
    recentRate: 95 + (seed % 6),
    averageReleaseTime: String(3 + (seed % 5)),
    averageTransferTime: String(2 + (seed % 4)),
    accountCreateDays: 400 + seed * 100,
    firstTradeDays: 300 + seed * 80,
    recentTradeAmount: String(5000 + seed * 2000),
    totalTradeAmount: String(50000 + seed * 15000),
    goodAppraiseRate: String(95 + (seed % 5)),
    goodAppraiseCount: 100 + seed * 25,
    badAppraiseCount: seed,
    authStatus: 1,
    blocked: 'N',
    vipLevel: seed % 3
  };
}

export function sendTradeMessageWithMock(
  tradeId: number,
  payload: {
    message: string;
    contentType: ChatContentType;
    fileName: string | null;
    fileUrl?: string | null;
  }
): ChatMessage {
  const state = getMockState();
  const trade = state.trades.find((item) => tradeId === item.id);

  if (undefined === trade) {
    throw new Error('Сделка не найдена.');
  }

  const message = createChatMessage(state, tradeId, {
    senderType: 'user',
    message: payload.message,
    contentType: payload.contentType,
    fileName: payload.fileName ?? null,
    fileUrl: payload.fileUrl ?? null
  });
  commitMockState();

  return clone(message);
}

export function getChatScriptsWithMock(): MockChatScriptRecord[] {
  return clone(getMockState().chatScripts);
}

export function createChatScriptWithMock(payload: Pick<MockChatScriptRecord, 'name' | 'isActive' | 'steps'>): MockChatScriptRecord {
  const state = getMockState();
  const createdAt = nowIso();
  const script: MockChatScriptRecord = {
    id: state.nextIds.chatScript++,
    name: payload.name,
    isActive: payload.isActive,
    steps: payload.steps.map((step, index) => ({
      id: state.nextIds.chatScriptStep++,
      sort: index + 1,
      message: step.message,
      delaySeconds: step.delaySeconds,
      contentType: step.contentType ?? 'str',
      fileName: step.fileName ?? null,
      fileUrl: step.fileUrl ?? null
    })),
    advertisementsCount: 0,
    createdAt,
    updatedAt: createdAt
  };

  state.chatScripts.unshift(script);
  commitMockState();

  return clone(script);
}

export function updateChatScriptWithMock(
  id: number,
  payload: Pick<MockChatScriptRecord, 'name' | 'isActive' | 'steps'>
): MockChatScriptRecord {
  const state = getMockState();
  const script = state.chatScripts.find((item) => id === item.id);

  if (undefined === script) {
    throw new Error('Сценарий не найден.');
  }

  script.name = payload.name;
  script.isActive = payload.isActive;
  script.steps = payload.steps.map((step, index) => ({
    id: step.id ?? state.nextIds.chatScriptStep++,
    sort: index + 1,
    message: step.message,
    delaySeconds: step.delaySeconds,
    contentType: step.contentType ?? 'str',
    fileName: step.fileName ?? null,
    fileUrl: step.fileUrl ?? null
  }));
  script.updatedAt = nowIso();
  commitMockState();

  return clone(script);
}

export function deleteChatScriptWithMock(id: number): void {
  const state = getMockState();
  state.chatScripts = state.chatScripts.filter((item) => id !== item.id);
  state.advertisements = state.advertisements.map((advertisement) => {
    if (id !== advertisement.chatScriptId) {
      return advertisement;
    }

    return {
      ...advertisement,
      chatScriptId: null,
      updatedAt: nowIso()
    };
  });
  commitMockState();
}
