import { isMockApiEnabled } from './config';
import { generateTradeForActiveAdvertisement, getMockStateSnapshot, resetMockState } from './database';

declare global {
  interface Window {
    __REBIT_MOCKS__?: {
      reset(): void;
      snapshot(): ReturnType<typeof getMockStateSnapshot>;
      createTrade(advertisementId?: number): ReturnType<typeof generateTradeForActiveAdvertisement>;
    };
  }
}

export function initializeMockRuntime(): void {
  if ('undefined' === typeof window || !isMockApiEnabled) {
    return;
  }

  window.__REBIT_MOCKS__ = {
    reset(): void {
      resetMockState();
    },
    snapshot() {
      return getMockStateSnapshot();
    },
    createTrade(advertisementId?: number) {
      return generateTradeForActiveAdvertisement(advertisementId);
    }
  };
}

export function createMockTradeScenario(advertisementId?: number) {
  if (!isMockApiEnabled) {
    return null;
  }

  return generateTradeForActiveAdvertisement(advertisementId);
}
