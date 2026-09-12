<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { HistoryIcon, PlugConnectedIcon, WalletIcon } from 'vue-tabler-icons';
import type { Balance } from '@/api/wallet';
import { useAuthStore } from '@/stores/auth';
import { useWalletStore } from '@/stores/wallet';
import { useIdentityStore } from '@/stores/identity';
import { useExchangeStore } from '@/stores/exchange';
import { useTransactionLabels } from '@/composables/useTransactionLabels';
import { useCurrencyFormat } from '@/composables/useCurrencyFormat';
import type { OrderBookEntry } from '@/api/exchange';
import AppEmptyState from '@/components/shared/AppEmptyState.vue';
import CurrencyIcon from '@/components/shared/CurrencyIcon.vue';

const auth = useAuthStore();
const wallet = useWalletStore();
const identity = useIdentityStore();
const exchange = useExchangeStore();
const { txLabel, txColor, txIcon } = useTransactionLabels();
const { formatRub } = useCurrencyFormat();
const isPageLoading = ref(true);

const userDisplayName = computed(() => auth.user?.['name'] ?? auth.user?.['email'] ?? '');
const hasConnectionMode = computed(() => null !== identity.connectionStatus?.['mode']);

const latestTransactions = computed(() => wallet.transactions.slice(0, 4));
const lockedBalancesCount = computed(() => wallet.balances.filter((balance) => parseAmount(balance.locked) > 0).length);
const sortedBalances = computed(() => {
  return [...wallet.balances].sort((left, right) => parseAmount(right.total) - parseAmount(left.total));
});

const bestBuyOrder = computed(() => {
  return exchange.buyOrders.reduce(
    (best, current) => {
      if (null === best || parseAmount(current.price) > parseAmount(best.price)) {
        return current;
      }

      return best;
    },
    null as OrderBookEntry | null
  );
});
const bestSellOrder = computed(() => {
  return exchange.sellOrders.reduce(
    (best, current) => {
      if (null === best || parseAmount(current.price) < parseAmount(best.price)) {
        return current;
      }

      return best;
    },
    null as OrderBookEntry | null
  );
});
const spread = computed(() => {
  if (null === bestBuyOrder.value || null === bestSellOrder.value) {
    return null;
  }

  return parseAmount(bestSellOrder.value.price) - parseAmount(bestBuyOrder.value.price);
});
const spreadPercent = computed(() => {
  if (null === spread.value || null === bestSellOrder.value) {
    return null;
  }

  const bestSellPrice = parseAmount(bestSellOrder.value.price);

  if (0 === bestSellPrice) {
    return null;
  }

  return (spread.value / bestSellPrice) * 100;
});
const lastTransactionDate = computed(() => {
  const [lastTransaction] = wallet.transactions;

  if (undefined === lastTransaction) {
    return 'Нет операций';
  }

  return formatDate(lastTransaction.createdAt);
});
const dashboardMetrics = computed(() => [
  {
    title: 'Общий баланс',
    value: null === wallet.totalRubEquivalent ? '—' : formatRub(wallet.totalRubEquivalent),
    description: 'Приблизительная стоимость всех активов в рублях',
    color: 'secondary',
    icon: 'mdi-currency-rub'
  },
  {
    title: 'Статус Bybit API',
    value: identity.statusLabel ?? connectionStateText(),
    description: hasConnectionMode.value ? `Режим: ${identity.modeLabel}` : 'Подключите API для торговли и синхронизации данных',
    color: connectionStateColor(),
    icon: identity.hasActiveConnection ? 'mdi-shield-check-outline' : identity.isConnected ? 'mdi-alert-outline' : 'mdi-link-variant-off'
  },
  {
    title: 'Заблокированные позиции',
    value: lockedBalancesCount.value,
    description: 'Показывает, по скольким валютам есть заблокированные средства',
    color: 'warning',
    icon: 'mdi-lock-outline'
  },
  {
    title: 'Последняя активность',
    value: lastTransactionDate.value,
    description: 'Последняя транзакция или обновление истории операций',
    color: 'info',
    icon: 'mdi-history'
  }
]);
const importantNotices = computed(() => {
  const notices: Array<{
    key: string;
    title: string;
    description: string;
    color: 'warning' | 'success' | 'info';
    icon: string;
    background: string;
    actionLabel?: string;
    actionTo?: string;
  }> = [];

  if (!identity.hasActiveConnection) {
    notices.push({
      key: 'connection-required',
      title: 'Подключение требует действия',
      description: 'Без активного Bybit API часть функций кабинета и рынок P2P будут недоступны.',
      color: 'warning',
      icon: 'mdi-link-variant-off',
      background: 'rgba(255, 193, 7, 0.08)',
      actionLabel: 'Подключить API',
      actionTo: '/profile/api-connection'
    });
  } else {
    notices.push({
      key: 'connection-active',
      title: 'API подключён',
      description: `Можно работать со стаканом и актуальными данными по паре ${exchange.selectedPair.label}.`,
      color: 'success',
      icon: 'mdi-shield-check-outline',
      background: 'rgba(0, 200, 83, 0.07)'
    });
  }

  if (0 < lockedBalancesCount.value) {
    notices.push({
      key: 'locked-balances',
      title: 'Есть заблокированные средства',
      description: `Проверьте активные объявления и сделки: блокировки найдены по ${lockedBalancesCount.value} валютам.`,
      color: 'info',
      icon: 'mdi-lock-outline',
      background: 'rgba(3, 201, 215, 0.07)'
    });
  }

  if (0 === latestTransactions.value.length) {
    notices.push({
      key: 'transactions-empty',
      title: 'История пока пустая',
      description: 'Когда появятся первые операции, они отобразятся в блоке последних транзакций ниже.',
      color: 'info',
      icon: 'mdi-history',
      background: 'rgba(3, 201, 215, 0.07)'
    });
  }

  return notices;
});

function parseAmount(value: string | number): number {
  const parsedValue = Number.parseFloat(String(value));

  return Number.isNaN(parsedValue) ? 0 : parsedValue;
}

function formatAmount(value: string | number, maximumFractionDigits = 2): string {
  const parsedValue = 'number' === typeof value ? value : parseAmount(value);

  return new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits
  }).format(parsedValue);
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString('ru-RU');
}

function hasRubEquivalent(balance: Balance): boolean {
  return undefined !== balance.rubEquivalent && null !== balance.rubEquivalent;
}

function connectionStateText(): string {
  if (identity.hasActiveConnection) {
    return 'Подключение активно';
  }

  if (identity.isConnected) {
    return identity.statusLabel ?? 'Подключение требует внимания';
  }

  return 'API не подключён';
}

function connectionStateColor(): string {
  const connectionStatus = identity.connectionStatus;

  if (null === connectionStatus) {
    return identity.isConnected ? 'warning' : 'error';
  }

  switch (connectionStatus['status']) {
    case 'active':
      return 'success';
    case 'pending_verification':
      return 'info';
    case 'invalid':
      return 'warning';
    case 'revoked':
      return 'error';
    default:
      return identity.isConnected ? 'warning' : 'error';
  }
}

async function loadDashboard(): Promise<void> {
  isPageLoading.value = true;

  try {
    await identity.fetchStatus();
    exchange.setOrderBookAccess(identity.hasActiveConnection);

    const tasks: Array<Promise<void>> = [
      wallet.fetchBalances(),
      wallet.fetchTransactions({ limit: 4, offset: 0 }),
      exchange.fetchCurrencyPairs()
    ];

    if (identity.hasActiveConnection) {
      tasks.push(exchange.fetchOrderBook());
    }

    await Promise.allSettled(tasks);
  } finally {
    isPageLoading.value = false;
  }
}

onMounted(async () => {
  await loadDashboard();
});
</script>

<template>
  <div class="dashboard-page">
    <v-card class="dashboard-hero mb-6" rounded="lg">
      <v-card-text class="pa-6 pa-md-8">
        <v-row align="center">
          <v-col cols="12" md="8">
            <div class="d-flex align-center flex-wrap ga-3 mb-4">
              <v-chip :color="connectionStateColor()" variant="tonal" size="small" class="font-weight-bold">
                {{ connectionStateText() }}
              </v-chip>
              <v-chip v-if="hasConnectionMode" color="primary" variant="tonal" size="small" class="font-weight-bold">
                {{ identity.modeLabel }}
              </v-chip>
              <v-chip color="secondary" variant="tonal" size="small" class="font-weight-bold">
                Пара: {{ exchange.selectedPair.label }}
              </v-chip>
            </div>

            <h1 class="text-h4 text-md-h3 font-weight-bold mb-2">Добро пожаловать, {{ userDisplayName }}</h1>
            <p class="text-body-1 text-medium-emphasis mb-0 dashboard-hero__subtitle">
              Следите за текущим состоянием балансов, последними операциями и лучшими предложениями рынка в одном месте.
            </p>
          </v-col>

          <v-col cols="12" md="4">
            <div class="d-flex flex-column ga-3 dashboard-hero__actions">
              <v-btn color="white" size="large" prepend-icon="mdi-swap-horizontal-bold" to="/orderbook" class="text-secondary">
                Открыть P2P стакан
              </v-btn>
              <v-btn color="white" size="large" prepend-icon="mdi-link-variant" to="/profile/api-connection" class="text-secondary">
                Настроить Bybit API
              </v-btn>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-alert v-if="identity.error" type="error" variant="tonal" class="mb-4">{{ identity.error }}</v-alert>
    <v-alert v-if="wallet.error" type="error" variant="tonal" class="mb-4">{{ wallet.error }}</v-alert>
    <v-alert v-if="exchange.error" type="error" variant="tonal" class="mb-4">{{ exchange.error }}</v-alert>

    <v-row v-if="isPageLoading" justify="center" class="mt-8">
      <v-progress-circular indeterminate color="primary" size="56" />
    </v-row>

    <template v-else>
      <v-row class="mb-2">
        <v-col v-for="metric in dashboardMetrics" :key="metric.title" cols="12" sm="6" xl="3">
          <v-card class="dashboard-card dashboard-metric-card" rounded="lg">
            <v-card-item class="dashboard-card__header dashboard-card__header--compact px-5 py-4">
              <template #prepend>
                <v-avatar size="42" :color="metric.color" variant="tonal">
                  <v-icon>{{ metric.icon }}</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-body-2 font-weight-medium text-medium-emphasis">{{ metric.title }}</v-card-title>
            </v-card-item>

            <v-divider class="dashboard-card__divider" />

            <v-card-text class="dashboard-card__body pa-5">
              <div class="text-h6 font-weight-bold mb-1">{{ metric.value }}</div>
              <div class="text-body-2 text-medium-emphasis">{{ metric.description }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" xl="8">
          <v-card class="dashboard-card h-100" rounded="lg">
            <v-card-item class="dashboard-card__header px-5 py-4">
              <template #prepend>
                <v-avatar size="42" color="primary" variant="tonal">
                  <v-icon>mdi-wallet</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-h6 font-weight-bold">Текущее состояние балансов</v-card-title>
              <v-card-subtitle>Ключевые валюты и доступные средства на вашем аккаунте</v-card-subtitle>
              <template #append>
                <v-btn variant="text" color="primary" to="/wallet/balances">Все балансы</v-btn>
              </template>
            </v-card-item>

            <v-divider class="dashboard-card__divider" />

            <v-card-text class="dashboard-card__body pa-5">
              <!-- Общий баланс в рублях -->
              <v-sheet v-if="null !== wallet.totalRubEquivalent" class="dashboard-total-rub pa-4 mb-4" rounded="lg">
                <div class="d-flex align-center ga-3">
                  <v-avatar size="44" color="primary" variant="tonal">
                    <v-icon>mdi-currency-rub</v-icon>
                  </v-avatar>
                  <div>
                    <div class="text-caption text-medium-emphasis">Общий баланс (приблизительно)</div>
                    <div class="text-h5 font-weight-bold">{{ formatRub(wallet.totalRubEquivalent) }}</div>
                  </div>
                </div>
              </v-sheet>

              <v-row v-if="0 < sortedBalances.length">
                <v-col v-for="balance in sortedBalances" :key="balance.currency" cols="12" md="6">
                  <v-sheet class="dashboard-mini-card pa-4" rounded="lg">
                    <div class="d-flex align-start justify-space-between ga-3 mb-4">
                      <div class="d-flex align-center ga-3">
                        <CurrencyIcon :code="balance.currency" :size="44" />
                        <div>
                          <div class="text-subtitle-1 font-weight-bold">{{ balance.currency }}</div>
                          <div class="text-body-2 text-medium-emphasis">Всего: {{ formatAmount(balance.total, 8) }}</div>
                        </div>
                      </div>

                      <v-chip :color="parseAmount(balance.locked) > 0 ? 'warning' : 'success'" variant="tonal" size="small">
                        {{ parseAmount(balance.locked) > 0 ? 'Есть блокировка' : 'Доступно' }}
                      </v-chip>
                    </div>

                    <div class="d-flex justify-space-between ga-3 mb-2">
                      <div>
                        <div class="text-caption text-medium-emphasis">Доступно</div>
                        <div class="text-h6 font-weight-bold">{{ formatAmount(balance.available, 8) }}</div>
                      </div>
                      <div class="text-right">
                        <div class="text-caption text-medium-emphasis">Заблокировано</div>
                        <div class="text-subtitle-1 font-weight-medium">{{ formatAmount(balance.locked, 8) }}</div>
                      </div>
                    </div>
                    <div v-if="hasRubEquivalent(balance)" class="text-body-2 text-primary font-weight-medium">
                      ≈ {{ formatRub(balance.rubEquivalent ?? 0) }}
                    </div>
                  </v-sheet>
                </v-col>
              </v-row>

              <AppEmptyState
                v-else
                :icon="WalletIcon"
                tone="primary"
                title="Балансы пока пусты"
                description="Подключите Bybit API, чтобы видеть текущее состояние средств и синхронизацию кошелька прямо на дашборде."
                align="left"
                compact
              >
                <template #actions>
                  <div class="d-flex justify-start">
                    <v-btn color="secondary" variant="outlined" to="/profile/api-connection">
                      <template #prepend>
                        <PlugConnectedIcon :size="18" stroke-width="1.75" />
                      </template>
                      Подключить Bybit API
                    </v-btn>
                  </div>
                </template>
              </AppEmptyState>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" xl="4">
          <div class="d-flex flex-column ga-6 h-100">
            <v-card class="dashboard-card" rounded="lg">
              <v-card-item class="dashboard-card__header px-5 py-4">
                <template #prepend>
                  <v-avatar size="42" color="secondary" variant="tonal">
                    <v-icon>mdi-trending-up</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-h6 font-weight-bold">Выгодная покупка и продажа</v-card-title>
                <v-card-subtitle>Лучшие цены по паре {{ exchange.selectedPair.label }}</v-card-subtitle>
              </v-card-item>

              <v-divider class="dashboard-card__divider" />

              <v-card-text class="dashboard-card__body pa-5">
                <template v-if="identity.hasActiveConnection && null !== bestBuyOrder && null !== bestSellOrder">
                  <v-sheet class="dashboard-market-card dashboard-market-card--buy pa-4 mb-4" rounded="lg">
                    <div class="d-flex align-start ga-4">
                      <v-avatar size="44" color="success" variant="tonal" class="flex-shrink-0">
                        <v-icon>mdi-arrow-bottom-left</v-icon>
                      </v-avatar>

                      <div class="flex-grow-1">
                        <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-2">
                          <span class="text-body-2 font-weight-medium">Лучшая покупка</span>
                          <v-chip color="success" variant="tonal" size="small">Buy</v-chip>
                        </div>
                        <div class="text-h5 font-weight-bold mb-1">{{ formatAmount(bestBuyOrder.price) }} ₽</div>
                        <div class="text-body-2 text-medium-emphasis mb-2">{{ bestBuyOrder.username }}</div>
                        <div class="text-caption text-medium-emphasis">
                          Лимит: {{ formatAmount(bestBuyOrder.minLimit) }} — {{ formatAmount(bestBuyOrder.maxLimit) }} ₽
                        </div>
                      </div>
                    </div>
                  </v-sheet>

                  <v-sheet class="dashboard-market-card dashboard-market-card--sell pa-4 mb-4" rounded="lg">
                    <div class="d-flex align-start ga-4">
                      <v-avatar size="44" color="error" variant="tonal" class="flex-shrink-0">
                        <v-icon>mdi-arrow-top-right</v-icon>
                      </v-avatar>

                      <div class="flex-grow-1">
                        <div class="d-flex align-center justify-space-between flex-wrap ga-2 mb-2">
                          <span class="text-body-2 font-weight-medium">Лучшая продажа</span>
                          <v-chip color="error" variant="tonal" size="small">Sell</v-chip>
                        </div>
                        <div class="text-h5 font-weight-bold mb-1">{{ formatAmount(bestSellOrder.price) }} ₽</div>
                        <div class="text-body-2 text-medium-emphasis mb-2">{{ bestSellOrder.username }}</div>
                        <div class="text-caption text-medium-emphasis">
                          Лимит: {{ formatAmount(bestSellOrder.minLimit) }} — {{ formatAmount(bestSellOrder.maxLimit) }} ₽
                        </div>
                      </div>
                    </div>
                  </v-sheet>

                  <v-sheet
                    v-if="null !== spread && null !== spreadPercent"
                    class="dashboard-notice dashboard-notice--info pa-4"
                    rounded="lg"
                  >
                    <div class="dashboard-notice__layout">
                      <v-avatar size="44" color="info" variant="tonal" class="flex-shrink-0">
                        <v-icon>mdi-chart-timeline-variant</v-icon>
                      </v-avatar>

                      <div class="dashboard-notice__content">
                        <div class="text-subtitle-2 font-weight-bold mb-1">Текущий спред</div>
                        <p class="dashboard-notice__text text-body-2 text-medium-emphasis">
                          {{ formatAmount(spread) }} ₽
                          <span class="font-weight-medium">({{ formatAmount(spreadPercent) }}%)</span>
                        </p>
                      </div>
                    </div>
                  </v-sheet>
                </template>

                <v-sheet v-else-if="!identity.hasActiveConnection" class="dashboard-notice dashboard-notice--warning pa-4" rounded="lg">
                  <div class="dashboard-notice__layout">
                    <v-avatar size="44" color="warning" variant="tonal" class="flex-shrink-0">
                      <v-icon>mdi-link-variant-off</v-icon>
                    </v-avatar>

                    <div class="dashboard-notice__content">
                      <div class="text-subtitle-2 font-weight-bold mb-1">Рынок пока недоступен</div>
                      <p class="dashboard-notice__text text-body-2 text-medium-emphasis mb-3">
                        Подключите активный Bybit API, чтобы видеть лучшие предложения рынка на дашборде.
                      </p>
                      <v-btn size="small" variant="text" color="primary" to="/profile/api-connection"> Настроить Bybit API </v-btn>
                    </div>
                  </div>
                </v-sheet>

                <v-sheet v-else class="dashboard-notice dashboard-notice--info pa-4" rounded="lg">
                  <div class="dashboard-notice__layout">
                    <v-avatar size="44" color="info" variant="tonal" class="flex-shrink-0">
                      <v-icon>mdi-information-outline</v-icon>
                    </v-avatar>

                    <div class="dashboard-notice__content">
                      <div class="text-subtitle-2 font-weight-bold mb-1">Нет данных по лучшим предложениям</div>
                      <p class="dashboard-notice__text text-body-2 text-medium-emphasis">
                        Для выбранной пары пока нет доступных предложений на покупку и продажу.
                      </p>
                    </div>
                  </div>
                </v-sheet>
              </v-card-text>
            </v-card>

            <v-card class="dashboard-card flex-grow-1" rounded="lg">
              <v-card-item class="dashboard-card__header px-5 py-4">
                <template #prepend>
                  <v-avatar size="42" color="info" variant="tonal">
                    <v-icon>mdi-bell-outline</v-icon>
                  </v-avatar>
                </template>
                <v-card-title class="text-h6 font-weight-bold">Важная информация</v-card-title>
                <v-card-subtitle>То, на что стоит обратить внимание прямо сейчас</v-card-subtitle>
              </v-card-item>

              <v-divider class="dashboard-card__divider" />

              <v-card-text class="dashboard-card__body pa-5 d-flex flex-column ga-3">
                <v-sheet
                  v-for="notice in importantNotices"
                  :key="notice.key"
                  class="dashboard-notice pa-4"
                  :style="{ background: notice.background }"
                  rounded="lg"
                >
                  <div class="dashboard-notice__layout">
                    <v-avatar :color="notice.color" size="44" variant="tonal" class="flex-shrink-0">
                      <v-icon>{{ notice.icon }}</v-icon>
                    </v-avatar>

                    <div class="dashboard-notice__content">
                      <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-1">
                        <div class="text-subtitle-2 font-weight-bold">{{ notice.title }}</div>
                        <v-btn v-if="notice.actionTo" size="small" variant="text" color="primary" :to="notice.actionTo">
                          {{ notice.actionLabel }}
                        </v-btn>
                      </div>

                      <p class="dashboard-notice__text text-body-2 text-medium-emphasis">
                        {{ notice.description }}
                      </p>
                    </div>
                  </div>
                </v-sheet>
              </v-card-text>
            </v-card>
          </div>
        </v-col>
      </v-row>

      <v-row class="mt-1">
        <v-col cols="12" lg="8">
          <v-card class="dashboard-card h-100" rounded="lg">
            <v-card-item class="dashboard-card__header px-5 py-4">
              <template #prepend>
                <v-avatar size="42" color="info" variant="tonal">
                  <v-icon>mdi-history</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-h6 font-weight-bold">Последние транзакции</v-card-title>
              <v-card-subtitle>Последние изменения по балансу и торговым операциям</v-card-subtitle>
              <template #append>
                <v-btn variant="text" color="primary" to="/wallet/transactions">Вся история</v-btn>
              </template>
            </v-card-item>

            <v-divider class="dashboard-card__divider" />

            <v-card-text class="pa-0">
              <v-list v-if="0 < latestTransactions.length" lines="two">
                <v-list-item v-for="tx in latestTransactions" :key="tx.id" class="px-5 py-3">
                  <template #prepend>
                    <v-avatar size="40" :color="txColor(tx.type)" variant="tonal">
                      <v-icon>
                        {{ txIcon(tx.type) }}
                      </v-icon>
                    </v-avatar>
                  </template>

                  <v-list-item-title class="d-flex align-center flex-wrap ga-2">
                    <span class="font-weight-medium">{{ txLabel(tx.type) }}</span>
                    <v-chip size="x-small" variant="tonal" :color="txColor(tx.type)">{{ tx.currency }}</v-chip>
                  </v-list-item-title>

                  <v-list-item-subtitle>
                    {{ formatDate(tx.createdAt) }}
                    <span v-if="null !== tx.tradeId"> · Сделка #{{ tx.tradeId }}</span>
                  </v-list-item-subtitle>

                  <template #append>
                    <div class="text-right">
                      <div class="font-weight-bold">{{ formatAmount(tx.amount, 8) }}</div>
                      <div class="text-caption text-medium-emphasis">{{ tx.currency }}</div>
                    </div>
                  </template>
                </v-list-item>
              </v-list>

              <AppEmptyState
                v-else
                :icon="HistoryIcon"
                tone="info"
                title="Сделок пока не было"
                description="Когда появятся первые транзакции, они сразу отобразятся в этом блоке и будут доступны в полной истории операций."
                align="left"
                compact
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <v-card class="dashboard-card h-100" rounded="lg">
            <v-card-item class="dashboard-card__header px-5 py-4">
              <template #prepend>
                <v-avatar size="42" color="success" variant="tonal">
                  <v-icon>mdi-lightning-bolt-outline</v-icon>
                </v-avatar>
              </template>
              <v-card-title class="text-h6 font-weight-bold">Быстрые действия</v-card-title>
              <v-card-subtitle>Часто используемые разделы кабинета</v-card-subtitle>
            </v-card-item>

            <v-divider class="dashboard-card__divider" />

            <v-card-text class="dashboard-card__body pa-5">
              <div class="d-flex flex-column ga-3">
                <v-btn color="secondary" variant="flat" block prepend-icon="mdi-swap-horizontal-bold" to="/orderbook">
                  Стакан заявок
                </v-btn>
                <v-btn color="secondary" variant="tonal" block prepend-icon="mdi-wallet-outline" to="/wallet/balances">
                  Открыть балансы
                </v-btn>
                <v-btn color="info" variant="tonal" block prepend-icon="mdi-history" to="/wallet/transactions">
                  Посмотреть транзакции
                </v-btn>
                <v-btn color="default" variant="outlined" block prepend-icon="mdi-account-circle-outline" to="/profile">
                  Мой профиль
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<style scoped lang="scss">
.dashboard-page {
  display: flex;
  flex-direction: column;
}

.dashboard-hero {
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.12), transparent 35%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.12), #ffffff);
  overflow: hidden;
}

.dashboard-hero__subtitle {
  max-width: 640px;
}

.dashboard-hero__actions {
  max-width: 320px;
  margin-left: auto;
}

.dashboard-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.dashboard-metric-card {
  height: 100%;
}

.dashboard-card__header {
  min-height: 96px;
}

.dashboard-card__header--compact {
  min-height: 76px;
}

.dashboard-card__divider {
  opacity: 1;
}

.dashboard-card__body {
  padding-top: 20px;
}

.dashboard-mini-card {
  border: 1px solid rgba(15, 23, 42, 0.07);
  background: rgba(255, 255, 255, 0.9);
}

.dashboard-total-rub {
  border: 1px solid rgba(30, 136, 229, 0.12);
  background:
    radial-gradient(circle at top right, rgba(94, 53, 177, 0.06), transparent 40%),
    linear-gradient(135deg, rgba(30, 136, 229, 0.06), #ffffff);
}

.dashboard-market-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
}

.dashboard-market-card--buy {
  background: rgba(0, 200, 83, 0.06);
}

.dashboard-market-card--sell {
  background: rgba(244, 67, 54, 0.05);
}

.dashboard-notice {
  border: 1px solid rgba(15, 23, 42, 0.08);
  background: rgba(255, 255, 255, 0.9);
}

.dashboard-notice--warning {
  background: rgba(255, 193, 7, 0.08);
}

.dashboard-notice--info {
  background: rgba(3, 201, 215, 0.07);
}

.dashboard-notice__layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.dashboard-notice__content {
  flex: 1 1 auto;
  min-width: 0;
}

.dashboard-notice__text {
  margin: 0;
  line-height: 1.6;
}

@media (max-width: 959px) {
  .dashboard-hero__actions {
    max-width: 100%;
    margin-left: 0;
  }

  .dashboard-notice__layout {
    gap: 12px;
  }
}
</style>
