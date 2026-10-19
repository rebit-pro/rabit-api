# #219: подвал без ссылки на студию rebit-pro.ru

## Цель

Убрать из подвала MoreFoto строку «Разработано в ReBit Studio» со ссылкой на https://rebit-pro.ru и выложить изменение на stage.
Указание владельца 19.10.2026: «Убери ссылку на студию rebit-pro.ru в подвале. Обнови прод».

## Контекст

- Строку `footer-developer` добавили в E7 (5f61914) в `LegalFooter.vue`, нижняя полоса подвала под реквизитами продавца.
- E2E `zzzz-storefront-windows.spec.ts` проверяет текст и ссылку на desktop и mobile.
- На main после выкладки 65bd194 менялись только документы: выкладка — только frontend.

## Scope

- `LegalFooter.vue`: удалить строку `footer-developer` и упоминание разработчика в комментарии UI-DEC-07.
- E2E: подвал виден, в нём нет «ReBit Studio» и ссылки на rebit-pro.ru.
- Frontend-only выкладка на stage после gate и merge.

### Вне scope

- Адрес отправителя писем — #218.
- `API_HOST="api.rebit-pro.ru"` в `frontend/docker/production/nginx/Dockerfile` — техническое значение по умолчанию, не видно покупателю.

## Риски

- Нижняя полоса подвала (`justify-content: space-between`) остаётся с одной строкой продавца — выравнивание влево, проверить на скриншотах.

## Checklist

- [x] S1. Код и E2E, быстрые проверки frontend.
- [ ] S2. PR, самопроверка (простой PR).
- [ ] S3. Полный gate `make test-e2e`, скриншоты desktop/mobile.
- [ ] S4. Merge, frontend-only выкладка на stage, smoke.

## Критерии приёмки

- На desktop и mobile в подвале нет «Разработано в ReBit Studio» и ссылки на rebit-pro.ru; строка продавца на месте.
- Изменение работает на https://app.morefoto36.ru.

## Тест-кейсы

| ID | Предусловия / действие | Ожидаемый результат | Проверка |
| --- | --- | --- | --- |
| FSL-T01 | Lint, typecheck, unit | Без ошибок | `npm run check`, `npm run test:commerce` в Playwright-образе |
| FSL-T02 | E2E E7 галерея desktop/mobile | Подвал виден, без `footer-developer`, без ссылки на rebit-pro.ru | `make test-e2e` |
| FSL-T03 | Скриншоты подвала desktop/mobile | Строка продавца без пустых мест | артефакты gate `e7-*-gallery.png` |
| FSL-T04 | Stage после выкладки | В HTML/JS бандле нет rebit-pro.ru, страница `/legal` 200, подвал без студии | curl + Playwright smoke |
