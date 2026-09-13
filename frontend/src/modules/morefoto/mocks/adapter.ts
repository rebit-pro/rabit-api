import { AxiosError, type AxiosAdapter, type AxiosResponse } from 'axios';
import { DemoError, loginWithDemo, logoutWithDemo } from './service';

export const moreFotoMockAdapter: AxiosAdapter = async (config) => {
  try {
    const path = new URL(config.url ?? '/', 'https://morefoto.test').pathname;
    let data: unknown;
    if (path === '/api/v1/auth/login' && config.method === 'post') {
      data = await loginWithDemo(typeof config.data === 'string' ? JSON.parse(config.data) : config.data);
    } else if (path === '/api/v1/auth/logout' && config.method === 'post') {
      logoutWithDemo(String(config.headers.Authorization ?? '').replace(/^Bearer /, ''));
      data = null;
    } else {
      throw new DemoError(404, 'Операция пока недоступна в MoreFoto.');
    }
    return { data, status: 200, statusText: 'OK', headers: {}, config };
  } catch (error) {
    const status = error instanceof DemoError ? error.status : 503;
    const message = error instanceof Error ? error.message : 'Не удалось выполнить запрос.';
    const response: AxiosResponse = { data: { message }, status, statusText: 'Error', headers: {}, config };
    throw new AxiosError(message, 'ERR_BAD_RESPONSE', config, undefined, response);
  }
};
