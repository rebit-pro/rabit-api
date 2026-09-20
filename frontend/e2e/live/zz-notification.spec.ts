import { expect, test } from '@playwright/test';

test('H1/NTF-01: существующий Lead остаётся доступен и honeypot не отправляет сообщение', async ({ request }) => {
  const response = await request.post('/api/v1/lead', {
    multipart: {
      name: 'Тестовый бот',
      phone: '+7 900 000-00-00',
      description: 'Проверка обратной совместимости Notification без реальной рассылки.',
      email: 'bot@example.com',
      company: 'honeypot-filled',
      page: 'https://example.test/e2e',
      source: 'h1-e2e'
    }
  });

  expect(response.status()).toBe(200);
  const payload = await response.json();
  expect(payload.data).toEqual({ accepted: true });
});
